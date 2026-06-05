<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait;
    use HasApiTokens;

    protected $fillable = ['index_number', 'first_name', 'middle_name', 'last_name', 'email', 'profile_image', 'phone_number', 'password', 'department_id', 'class_id', 'bound_ip'];

    protected $hidden = ['password'];


    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Which basic profile fields are still empty (first name, last name, phone).
     *
     * @return list<string>
     */
    public function missingBasicOnboardingFields(?bool $requireProfileImage = null): array
    {
        if ($requireProfileImage === null) {
            $requireProfileImage = SystemSetting::get()->require_profile_image_on_onboarding ?? true;
        }

        $missing = [];
        if (trim((string) ($this->first_name ?? '')) === '') {
            $missing[] = 'first_name';
        }
        if (trim((string) ($this->last_name ?? '')) === '') {
            $missing[] = 'last_name';
        }
        if ($requireProfileImage && trim((string) ($this->phone_number ?? '')) === '') {
            $missing[] = 'phone_number';
        }
        if ($requireProfileImage && trim((string) ($this->profile_image ?? '')) === '') {
            $missing[] = 'profile_image';
        }

        return $missing;
    }

    /**
     * First-time web onboarding: phone + legal name (before faculty/department).
     */
    public function needsBasicOnboarding(?bool $requireProfileImage = null): bool
    {
        return count($this->missingBasicOnboardingFields($requireProfileImage)) > 0;
    }

    public function hasCompletedProfile(): bool
    {
        if ($this->needsBasicOnboarding()) {
            return false;
        }

        return ! empty($this->department_id);
    }

    protected static function booted(): void
    {
        // Strip the optional `email` attribute on older deploys before the
        // 2026_06_05_121000_add_email_to_students_table migration has run, so
        // create()/update() never fail with "unknown column 'email'".
        static::saving(function (Student $student): void {
            if (! \App\Support\SchemaFeatures::hasStudentsEmail()
                && array_key_exists('email', $student->getAttributes())) {
                unset($student->attributes['email']);
            }
        });

        static::creating(function (Student $student) {
            // Index-only self-registration: clear profile until onboarding.
            // Imports and admin roster entry pass names — keep them.
            $hasProvidedName = filled($student->first_name)
                || filled($student->middle_name)
                || filled($student->last_name);
            if ($hasProvidedName) {
                return;
            }
            $student->first_name = null;
            $student->middle_name = null;
            $student->last_name = null;
            $student->phone_number = null;
            $student->profile_image = null;
        });

        static::saving(function (Student $student) {
            if (! empty($student->index_number)) {
                $student->index_number = strtoupper($student->index_number);
            }
        });

        static::deleting(function (Student $student) {
            if (! empty($student->index_number) && DeletedStudentIndex::tableReady()) {
                DeletedStudentIndex::query()->create([
                    'index_number' => strtoupper(trim((string) $student->index_number)),
                    'deleted_at' => now(),
                ]);
            }
            if (method_exists($student, 'tokens')) {
                $student->tokens()->delete();
            }
        });
    }

    /**
     * Create or overwrite a roster row by index (class list import / admin add).
     */
    public static function upsertFromRoster(string $indexNumber, array $attributes): self
    {
        $index = strtoupper(trim($indexNumber));

        return static::updateOrCreate(
            ['index_number' => $index],
            array_merge($attributes, ['index_number' => $index])
        );
    }

    /**
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeSearchTerm(Builder $query, ?string $search): Builder
    {
        $raw = trim((string) $search);
        if ($raw === '') {
            return $query;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $raw).'%';
        $normalized = preg_replace('/[\s\/\-]/', '', strtoupper($raw));

        return $query->where(function (Builder $q) use ($term, $normalized, $raw): void {
            $q->where('index_number', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('middle_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhereRaw(
                    "LOWER(CONCAT(COALESCE(first_name,''), ' ', COALESCE(middle_name,''), ' ', COALESCE(last_name,''))) LIKE ?",
                    ['%'.strtolower($raw).'%']
                );
            if (strlen($normalized) >= 3) {
                $q->orWhereRaw(
                    "UPPER(REPLACE(REPLACE(REPLACE(TRIM(index_number), ' ', ''), '/', ''), '-', '')) LIKE ?",
                    ['%'.$normalized.'%']
                );
            }
        });
    }

    public static function findByIndex(string $indexNumber): ?self
    {
        $indexNumber = strtoupper(trim((string) $indexNumber));
        if (empty($indexNumber)) {
            return null;
        }
        $student = static::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexNumber])->first();
        if ($student) {
            return $student;
        }
        $normalized = preg_replace('/[\s\/\-]/', '', $indexNumber);
        if (strlen($normalized) >= 4) {
            return static::whereRaw("UPPER(REPLACE(REPLACE(REPLACE(TRIM(index_number), ' ', ''), '/', ''), '-', '')) = ?", [$normalized])->first();
        }

        return null;
    }

    /**
     * Mask student ID for on-screen display (e.g. password steps) so the full ID isn’t shown on shared screens.
     */
    public static function maskIndexForDisplay(?string $indexNumber): string
    {
        if ($indexNumber === null || trim($indexNumber) === '') {
            return '—';
        }

        $s = strtoupper(trim($indexNumber));
        $len = strlen($s);
        if ($len <= 5) {
            return str_repeat('•', max(0, $len - 1)).substr($s, -1);
        }
        if ($len <= 10) {
            return substr($s, 0, 2).' ··· '.substr($s, -2);
        }

        return substr($s, 0, 4).' ··· '.substr($s, -3);
    }

    /**
     * Full name only (first + last). Empty until the student adds their name (profile / signup / import).
     */
    public function getDisplayName(): string
    {
        $parts = array_filter([
            trim((string) ($this->first_name ?? '')),
            trim((string) ($this->middle_name ?? '')),
            trim((string) ($this->last_name ?? '')),
        ], fn (string $s) => $s !== '');

        return implode(' ', $parts);
    }

    /**
     * Name if set, otherwise index number (for titles, APIs, messages).
     */
    public function getDisplayNameOrIndex(): string
    {
        $name = $this->getDisplayName();

        return $name !== '' ? $name : (string) ($this->index_number ?? '');
    }

    public function profileImageUrl(): ?string
    {
        if (! $this->profile_image) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $this->profile_image)) {
            return $this->profile_image;
        }

        // Same host as API (`/api/...`) so mobile clients using `API_BASE_URL` always resolve images.
        $url = URL::to('/api/students/'.$this->id.'/profile-image');
        $v = $this->updated_at?->timestamp ?? $this->id;

        return $url.'?v='.$v;
    }

    public function avatarInitials(): string
    {
        if (! empty($this->first_name) && ! empty($this->last_name)) {
            return strtoupper(substr($this->first_name, 0, 1).substr($this->last_name, 0, 1));
        }
        $idx = (string) ($this->index_number ?? '');
        if (strlen($idx) >= 2) {
            return strtoupper(substr($idx, 0, 2));
        }

        return $idx !== '' ? strtoupper($idx) : '—';
    }

    /**
     * Store a data-URL base64 image on the public disk; replaces any previous file.
     */
    public function saveProfileImageFromBase64(string $imageData): bool
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            return false;
        }
        $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData), true);
        if ($raw === false || $raw === '') {
            return false;
        }

        return $this->storeOptimizedProfileImage($raw);
    }

    /**
     * Store a web-uploaded profile photo with server-side optimization (max 500KB).
     */
    public function saveProfileImageFromUpload(UploadedFile $file): bool
    {
        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            return false;
        }

        return $this->storeOptimizedProfileImage($raw);
    }

    private function storeOptimizedProfileImage(string $raw): bool
    {
        $optimized = $this->optimizeProfileImageBinary($raw, 500 * 1024);
        if ($optimized === null || $optimized['binary'] === '') {
            return false;
        }

        if ($this->profile_image) {
            Storage::disk('public')->delete($this->profile_image);
        }

        $filename = 'students/'.$this->id.'_'.uniqid().'.'.$optimized['extension'];
        Storage::disk('public')->put($filename, $optimized['binary']);
        $this->profile_image = $filename;

        return true;
    }

    /**
     * Compress and resize while preserving visual quality and keeping <= $maxBytes.
     *
     * @return array{binary:string, extension:string}|null
     */
    private function optimizeProfileImageBinary(string $raw, int $maxBytes): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW < 2 || $srcH < 2) {
            imagedestroy($src);

            return null;
        }

        $maxDimension = 1280;
        $scale = min(1, $maxDimension / max($srcW, $srcH));
        $targetW = max(1, (int) floor($srcW * $scale));
        $targetH = max(1, (int) floor($srcH * $scale));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($src);

        $ext = 'jpg';
        $best = '';
        $bestLen = PHP_INT_MAX;
        $quality = 86;

        for ($pass = 0; $pass < 10; $pass++) {
            $binary = $this->renderProfileImageBinary($canvas, $ext, $quality);
            if ($binary === null) {
                continue;
            }

            $len = strlen($binary);
            if ($len < $bestLen) {
                $best = $binary;
                $bestLen = $len;
            }
            if ($len <= $maxBytes) {
                imagedestroy($canvas);

                return ['binary' => $binary, 'extension' => $ext];
            }

            if ($quality > 62) {
                $quality -= 6;

                continue;
            }

            $nextW = max(480, (int) floor(imagesx($canvas) * 0.9));
            $nextH = max(480, (int) floor(imagesy($canvas) * 0.9));
            if ($nextW === imagesx($canvas) && $nextH === imagesy($canvas)) {
                break;
            }

            $smaller = imagecreatetruecolor($nextW, $nextH);
            imagefill($smaller, 0, 0, imagecolorallocate($smaller, 255, 255, 255));
            imagecopyresampled($smaller, $canvas, 0, 0, 0, 0, $nextW, $nextH, imagesx($canvas), imagesy($canvas));
            imagedestroy($canvas);
            $canvas = $smaller;
        }

        imagedestroy($canvas);
        if ($best === '' || strlen($best) > $maxBytes) {
            return null;
        }

        return ['binary' => $best, 'extension' => $ext];
    }

    private function renderProfileImageBinary($image, string $ext, int $quality): ?string
    {
        ob_start();
        $ok = $ext === 'webp'
            ? imagewebp($image, null, $quality)
            : imagejpeg($image, null, $quality);
        $data = ob_get_clean();

        if (! $ok || ! is_string($data) || $data === '') {
            return null;
        }

        return $data;
    }

    public function getProgramLabel(): string
    {
        $idx = strtoupper($this->index_number ?? '');
        if (str_contains($idx, 'ITN')) {
            return 'Networking';
        }
        if (str_contains($idx, 'ITS')) {
            return 'Software';
        }
        if (str_contains($idx, 'ITD')) {
            return 'Data';
        }

        return '—';
    }

    public function isOnboarded(?bool $requireProfileImage = null): bool
    {
        if ($requireProfileImage === null) {
            $requireProfileImage = SystemSetting::get()->require_profile_image_on_onboarding ?? true;
        }

        $base = ! empty($this->phone_number) && (! $requireProfileImage || ! empty($this->profile_image));
        if (! $base) {
            return false;
        }

        return true;
    }

    public function classReps(): HasMany
    {
        return $this->hasMany(ClassRep::class, 'student_id');
    }

    public function reppedClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_reps', 'student_id', 'class_id')->withPivot('role')->withTimestamps();
    }

    public function isClassRep(): bool
    {
        if ($this->relationLoaded('classReps')) {
            if ($this->classReps->isNotEmpty()) {
                return true;
            }
        } elseif ($this->classReps()->exists()) {
            return true;
        }

        return false;
    }

    public function isRep(): bool
    {
        return $this->isClassRep();
    }

    /**
     * Class IDs this student may manage as a class rep (empty if not a rep).
     *
     * @return Collection<int, int>
     */
    public function repManagedClassIds(): Collection
    {
        $fromClass = $this->classReps()->pluck('class_id');

        return $fromClass
            ->merge(collect())
            ->unique()
            ->filter()
            ->values();
    }

    /**
     * Distinct class rep roles for API login / profile.
     *
     * @return list<array{class_id: int, role: string}>
     */
    public function apiRepRoleRows(): array
    {
        $this->loadMissing(['classReps']);
        $rows = [];
        $seen = [];
        foreach ($this->classReps as $cr) {
            $cid = (int) $cr->class_id;
            if ($cid <= 0 || isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            $rows[] = ['class_id' => $cid, 'role' => (string) $cr->role];
        }

        return $rows;
    }

    /**
     * Enrolled class only — students never see other classes' timetables via rep assignments.
     *
     * @return Collection<int, int>
     */
    public function timetableVisibleClassIds(): Collection
    {
        return $this->class_id ? collect([(int) $this->class_id]) : collect();
    }

    /**
     * Weekly lecture + credit-hour progress for timetable cards (web + mobile).
     *
     * @return array{lectures_total:int,lectures_taken:int,lectures_remaining:int,credit_hours_total:int,credit_hours_taken:int,credit_hours_remaining:int}
     */
    public function weeklyTimetableSummary(?Carbon $at = null): array
    {
        if (! $this->class_id) {
            return [
                'lectures_total' => 0,
                'lectures_taken' => 0,
                'lectures_remaining' => 0,
                'credit_hours_total' => 0,
                'credit_hours_taken' => 0,
                'credit_hours_remaining' => 0,
            ];
        }

        $classId = (int) $this->class_id;
        $perSlotCredits = collect();
        if (\App\Support\SchemaFeatures::hasClassTimetables() && \App\Support\ClassTimetableAccess::classHasEntries($classId)) {
            $entries = \App\Models\ClassTimetable::query()
                ->where('class_id', $classId)
                ->get(['course_id', 'credit_hours']);
            $perSlotCredits = $entries
                ->groupBy(fn ($e) => (int) $e->course_id)
                ->map(fn ($group) => $group->pluck('credit_hours')->filter()->first());
            $courseIds = $perSlotCredits->keys()->all();
            $courses = $courseIds === []
                ? collect()
                : Course::query()->whereIn('id', $courseIds)->get(['id', 'credit_hours']);
        } else {
            $courses = \App\Support\StudentCourseAccess::coursesQueryForStudent($this)
                ->whereNotNull('day_of_week')
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->get(['id', 'credit_hours']);
        }

        $lecturesTotal = $courses->count();
        if ($lecturesTotal === 0) {
            return [
                'lectures_total' => 0,
                'lectures_taken' => 0,
                'lectures_remaining' => 0,
                'credit_hours_total' => 0,
                'credit_hours_taken' => 0,
                'credit_hours_remaining' => 0,
            ];
        }

        // Prefer the per-class slot's credit_hours (set by the rep on the
        // timetable); fall back to the course's own value, then to 2.
        $courseCredits = $courses->mapWithKeys(function (Course $c) use ($perSlotCredits) {
            $perSlot = $perSlotCredits->get($c->id);
            $value = $perSlot !== null && $perSlot !== '' ? (int) $perSlot : (int) ($c->credit_hours ?? 2);
            return [$c->id => max(1, $value)];
        });
        $creditHoursTotal = $courseCredits->sum();

        $now = $at ?? now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $takenCourseIds = Attendance::query()
            ->where('student_id', $this->id)
            ->whereIn('course_id', $courseCredits->keys()->all())
            ->whereBetween('attendance_time', [$weekStart, $weekEnd])
            ->distinct()
            ->pluck('course_id');

        $lecturesTaken = $takenCourseIds->count();
        $creditHoursTaken = $takenCourseIds->sum(fn ($id) => (int) ($courseCredits->get($id) ?? 0));

        return [
            'lectures_total' => $lecturesTotal,
            'lectures_taken' => $lecturesTaken,
            'lectures_remaining' => max($lecturesTotal - $lecturesTaken, 0),
            'credit_hours_total' => $creditHoursTotal,
            'credit_hours_taken' => $creditHoursTaken,
            'credit_hours_remaining' => max($creditHoursTotal - $creditHoursTaken, 0),
        ];
    }

    /**
     * True if this student is a class rep for the class that owns the course (sessions / attendance rules).
     */
    public function isClassRepForCourse(int $courseId): bool
    {
        $course = Course::query()->find($courseId);
        if (! $course) {
            return false;
        }

        foreach ($course->assignedClassIds() as $classId) {
            if ($this->classReps()->where('class_id', $classId)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function deviceToken(): HasOne
    {
        return $this->hasOne(StudentDeviceToken::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
