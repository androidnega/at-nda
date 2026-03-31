<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait;
    use HasApiTokens;
    protected $fillable = ['index_number', 'first_name', 'middle_name', 'last_name', 'profile_image', 'phone_number', 'password', 'department_id', 'class_id', 'bound_ip'];

    protected $hidden = ['password'];

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Which basic profile fields are still empty (first name, last name, phone).
     *
     * @return list<string>
     */
    public function missingBasicOnboardingFields(): array
    {
        $missing = [];
        if (trim((string) ($this->first_name ?? '')) === '') {
            $missing[] = 'first_name';
        }
        if (trim((string) ($this->last_name ?? '')) === '') {
            $missing[] = 'last_name';
        }
        if (trim((string) ($this->phone_number ?? '')) === '') {
            $missing[] = 'phone_number';
        }
        if (trim((string) ($this->profile_image ?? '')) === '') {
            $missing[] = 'profile_image';
        }

        return $missing;
    }

    /**
     * First-time web onboarding: phone + legal name (before faculty/department).
     */
    public function needsBasicOnboarding(): bool
    {
        return count($this->missingBasicOnboardingFields()) > 0;
    }

    public function hasCompletedProfile(): bool
    {
        if ($this->needsBasicOnboarding()) {
            return false;
        }

        return !empty($this->department_id);
    }

    protected static function booted(): void
    {
        static::saving(function (Student $student) {
            if (!empty($student->index_number)) {
                $student->index_number = strtoupper($student->index_number);
            }
        });

        static::deleting(function (Student $student) {
            if (! empty($student->index_number)) {
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
            return str_repeat('•', max(0, $len - 1)) . substr($s, -1);
        }
        if ($len <= 10) {
            return substr($s, 0, 2) . ' ··· ' . substr($s, -2);
        }

        return substr($s, 0, 4) . ' ··· ' . substr($s, -3);
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

        return route('media.students.profile-image', ['student' => $this->id]) . '?v=' . $this->updated_at?->timestamp;
    }

    public function avatarInitials(): string
    {
        if (!empty($this->first_name) && !empty($this->last_name)) {
            return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
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
        if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
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

        $filename = 'students/' . $this->id . '_' . uniqid() . '.' . $optimized['extension'];
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
        if (!function_exists('imagecreatefromstring')) {
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

        $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
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

        if (!$ok || !is_string($data) || $data === '') {
            return null;
        }

        return $data;
    }

    public function getProgramLabel(): string
    {
        $idx = strtoupper($this->index_number ?? '');
        if (str_contains($idx, 'ITN')) return 'Networking';
        if (str_contains($idx, 'ITS')) return 'Software';
        if (str_contains($idx, 'ITD')) return 'Data';
        return '—';
    }

    public function isOnboarded(?bool $requireFace = null): bool
    {
        $base = !empty($this->phone_number) && !empty($this->profile_image);
        if (!$base) {
            return false;
        }
        return true;
    }

    public function courseReps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CourseRep::class);
    }

    public function classReps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClassRep::class, 'student_id');
    }

    public function reppedClasses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_reps', 'student_id', 'class_id')->withPivot('role')->withTimestamps();
    }

    public function reppedCourses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_reps')->withTimestamps();
    }

    public function isClassRep(): bool
    {
        return $this->classReps()->exists();
    }

    public function isRep(): bool
    {
        return $this->classReps()->exists() || $this->courseReps()->exists();
    }

    /**
     * Class IDs this student may manage as a rep (empty if not a rep).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function repManagedClassIds(): \Illuminate\Support\Collection
    {
        $fromClass = $this->classReps()->pluck('class_id')->unique();
        $fromCourse = $this->courseReps()->with('course')->get()->pluck('course.class_id')->filter()->unique();

        return $fromClass->merge($fromCourse)->unique()->filter();
    }

    /**
     * Class IDs whose courses / timetable this account may see (own class, or rep-managed classes).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function timetableVisibleClassIds(): \Illuminate\Support\Collection
    {
        if ($this->isRep()) {
            return $this->repManagedClassIds();
        }

        return $this->class_id ? collect([$this->class_id]) : collect();
    }

    /** True if this student is assigned as a course rep for the given course (can run session / attendance rules). */
    public function isCourseRepForCourse(int $courseId): bool
    {
        return $this->courseReps()->where('course_id', $courseId)->exists();
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
