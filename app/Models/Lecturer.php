<?php

namespace App\Models;

use App\Support\SchemaFeatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
        if ($this->class_id) {
            $fromPivot->push((int) $this->class_id);
        }

        return $fromPivot->merge($fromCourses)->map(fn ($id) => (int) $id)->unique()->values();
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
}
