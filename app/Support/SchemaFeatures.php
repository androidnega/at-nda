<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Cached checks for optional schema introduced after initial deploy.
 */
final class SchemaFeatures
{
    private static ?bool $classLecturerPivot = null;

    private static ?bool $courseClassPivot = null;

    public static function hasClassLecturerPivot(): bool
    {
        return self::$classLecturerPivot ??= Schema::hasTable('class_lecturer');
    }

    public static function hasCourseClassPivot(): bool
    {
        return self::$courseClassPivot ??= Schema::hasTable('course_class');
    }
}
