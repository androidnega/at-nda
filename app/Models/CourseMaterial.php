<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMaterial extends Model
{
    protected $fillable = [
        'course_id',
        'class_id',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by_student_id',
        'uploaded_by_lecturer_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'uploaded_by_student_id');
    }

    public function lecturerUploader(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'uploaded_by_lecturer_id');
    }

    /**
     * Friendly "Uploaded by …" string for badges.
     */
    public function uploadedByLabel(): string
    {
        if ($this->relationLoaded('lecturerUploader') && $this->lecturerUploader) {
            $name = method_exists($this->lecturerUploader, 'displayName')
                ? $this->lecturerUploader->displayName()
                : $this->lecturerUploader->name;

            return 'Lecturer · '.$name;
        }
        if ($this->uploaded_by_lecturer_id) {
            return 'Lecturer';
        }
        if ($this->relationLoaded('uploader') && $this->uploader) {
            $first = trim((string) ($this->uploader->first_name ?? ''));
            $last = trim((string) ($this->uploader->last_name ?? ''));
            $name = trim($first.' '.$last);

            return 'Rep'.($name !== '' ? ' · '.$name : '');
        }

        return $this->uploaded_by_lecturer_id ? 'Lecturer' : 'Rep';
    }

    public function uploaderRole(): string
    {
        return $this->uploaded_by_lecturer_id ? 'lecturer' : 'rep';
    }

    /**
     * Human-readable file size (e.g. "245 KB", "1.4 MB").
     */
    public function formattedFileSize(): string
    {
        $bytes = (int) ($this->file_size ?? 0);
        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) min(floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? (int) $value : number_format($value, $value >= 10 ? 0 : 1)).' '.$units[$power];
    }

    /**
     * Best-effort hint for the file icon used in the UI.
     */
    public function fileKind(): string
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_contains($mime, 'pdf')) return 'pdf';
        if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'doc';
        if (str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_contains($mime, 'csv')) return 'xls';
        if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return 'ppt';
        if (str_contains($mime, 'image')) return 'image';
        if (str_contains($mime, 'zip') || str_contains($mime, 'compressed') || str_contains($mime, 'rar')) return 'archive';
        if (str_contains($mime, 'audio')) return 'audio';
        if (str_contains($mime, 'video')) return 'video';
        if (str_contains($mime, 'text')) return 'text';

        return 'file';
    }
}
