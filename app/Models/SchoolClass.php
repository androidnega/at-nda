<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = ['name', 'code', 'faculty_id', 'department_id', 'level', 'semester_id', 'logo_path'];

    protected $casts = ['level' => 'integer'];

    /** Levels used in forms and progression (100 → 200 → …). */
    public const LEVELS = [100, 200, 300, 400];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'class_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->name,
            $this->level ? 'Level '.$this->level : null,
            $this->semester?->display_label,
        ]);
        return implode(' · ', $parts);
    }

    /** True when semester or level is missing / invalid (legacy rows). */
    public function needsAcademicMetadataReview(): bool
    {
        if ($this->semester_id === null) {
            return true;
        }
        $level = $this->level;

        return $level === null || ! in_array((int) $level, self::LEVELS, true);
    }

    /** Next level on the ladder, or null at 400; if level invalid, suggests 100. */
    public function suggestedNextLevel(): ?int
    {
        $level = (int) ($this->level ?? 0);
        $idx = array_search($level, self::LEVELS, true);
        if ($idx === false) {
            return self::LEVELS[0];
        }
        if ($idx >= count(self::LEVELS) - 1) {
            return null;
        }

        return self::LEVELS[$idx + 1];
    }
}
