<?php

namespace App\Models;

use App\Support\SchemaFeatures;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * Teaching staff directory entry (name + optional class). Mobile API login via email/username + password.
 * Courses link via lecturer_id; venue is set per course.
 */
class Lecturer extends Model
{
    use HasApiTokens;

    protected $fillable = ['name', 'class_id', 'email', 'username', 'password', 'must_change_password'];

    protected $hidden = ['password'];

    protected $casts = [
        'must_change_password' => 'boolean',
    ];

    protected static function booted(): void
    {
        // When a lecturer is renamed, propagate the new name to the cached
        // `courses.lecturer_name` column so legacy code paths and any future
        // PDFs keep showing the correct name even if the live Lecturer
        // relation can't be loaded for some reason.
        static::saved(function (Lecturer $lecturer): void {
            if (! $lecturer->wasChanged('name')) {
                return;
            }
            $name = trim((string) ($lecturer->name ?? ''));
            if ($name === '') {
                return;
            }
            try {
                Course::query()
                    ->where('lecturer_id', $lecturer->id)
                    ->where(function ($q) use ($name) {
                        $q->whereNull('lecturer_name')->orWhere('lecturer_name', '!=', $name);
                    })
                    ->update(['lecturer_name' => $name]);
            } catch (\Throwable $e) {
                // Cache backfill is best-effort; the live relation is the
                // source of truth, so swallow schema/DB errors silently.
            }
        });
    }

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_lecturer', 'lecturer_id', 'class_id')
            ->withTimestamps();
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Classes this lecturer may access (explicit assignments + courses they teach).
     *
     * @return Collection<int, int>
     */
    public function assignedClassIds(): Collection
    {
        $fromPivot = SchemaFeatures::hasClassLecturerPivot()
            ? $this->schoolClasses()->pluck('classes.id')
            : collect();
        $fromCourses = $this->courses()->whereNotNull('class_id')->distinct()->pluck('class_id');
        if (SchemaFeatures::hasCourseClassPivot()) {
            $courseIds = $this->courses()->pluck('id');
            if ($courseIds->isNotEmpty()) {
                $fromPivot = $fromPivot->merge(
                    DB::table('course_class')
                        ->whereIn('course_id', $courseIds)
                        ->distinct()
                        ->pluck('class_id')
                );
            }
        }
        if ($this->class_id) {
            $fromPivot->push((int) $this->class_id);
        }

        return $fromPivot->merge($fromCourses)->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function hasStaffLogin(): bool
    {
        return ! empty($this->password);
    }

    /**
     * Friendly surname for dashboards/greetings. Strips common honorifics
     * (Mr., Dr., Prof., Miss, etc.) and returns the last token in proper
     * title-case so that "MR. HILARY ACKAH-ARTHUR" → "Ackah-Arthur" and
     * "papa kweku abaidoo" → "Abaidoo".
     */
    public function displayLastName(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name === '') {
            return '';
        }
        // Strip leading honorifics (repeated, e.g. "Dr. Mr.").
        $honorifics = '/^(mr|mrs|ms|miss|mx|dr|prof|professor|rev|reverend|sir|madam|madame|hon|honorable|engr|engineer)\.?\s+/i';
        while (preg_match($honorifics, $name)) {
            $name = (string) preg_replace($honorifics, '', $name);
        }
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if ($parts === []) {
            return '';
        }
        $last = (string) end($parts);
        // Drop trailing punctuation that sneaks in from CSV imports.
        $last = trim($last, " \t\n\r\0\x0B.,;:");
        if ($last === '') {
            return '';
        }

        // mb_convert_case treats hyphens as word boundaries, so "ACKAH-ARTHUR"
        // becomes "Ackah-Arthur" correctly.
        return \Illuminate\Support\Str::title(mb_strtolower($last));
    }

    /**
     * Proper-cased full name for headers/PDFs. "MR. JOSEPH DANSO" → "Mr. Joseph Danso".
     */
    public function displayName(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name === '') {
            return '';
        }

        return \Illuminate\Support\Str::title(mb_strtolower($name));
    }

    public function staffLoginLabel(): string
    {
        if (! $this->hasStaffLogin()) {
            return '';
        }
        if ($this->username) {
            return (string) $this->username;
        }
        if ($this->email) {
            return (string) $this->email;
        }

        return 'Password set';
    }

    public function syncAssignedClasses(array $classIds): void
    {
        if (! SchemaFeatures::hasClassLecturerPivot()) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        $this->schoolClasses()->sync($ids);
        $this->class_id = $ids[0] ?? null;
        $this->save();
    }

    /**
     * @return EloquentCollection<int, SchoolClass>
     */
    public function assignedSchoolClasses(): EloquentCollection
    {
        $ids = $this->assignedClassIds();
        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        return SchoolClass::query()
            ->withCount('students')
            ->with(['faculty', 'department'])
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Course>
     */
    public function teachingCourses(): EloquentCollection
    {
        return $this->courses()
            ->with([
                'schoolClasses',
                'schoolClass',
                'venueRelation',
                'attendanceWeeks' => fn ($q) => $q->orderBy('week_number'),
            ])
            ->orderBy('course_name')
            ->get();
    }

    public function managesCourse(Course $course): bool
    {
        return (int) $this->id === (int) $course->lecturer_id;
    }

    /** Comma-separated class names for dropdowns (multi-class aware). */
    public function assignedClassesLabel(): string
    {
        if (SchemaFeatures::hasClassLecturerPivot()) {
            if ($this->relationLoaded('schoolClasses') && $this->schoolClasses->isNotEmpty()) {
                return $this->schoolClasses->pluck('name')->join(', ');
            }
            if ($this->schoolClasses()->exists()) {
                return $this->schoolClasses()->orderBy('name')->pluck('name')->join(', ');
            }
        }

        return $this->schoolClass?->name ?? '';
    }
}
