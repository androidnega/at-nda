<?php

namespace App\Support;

use App\Models\Student;
use App\Services\AttendanceInsightsService;
use Illuminate\Support\Facades\Cache;

/**
 * Students in a rep's classes with ≥ N consecutive missed sessions (any course).
 */
final class RepFlaggedStudents
{
    public const THRESHOLD = 3;

    public const CACHE_SECONDS = 300;

    /**
     * @return list<array{student_id: int, index_number: string, name: string, consecutive_missed: int, course_id: ?int, course_name: ?string}>
     */
    public static function forRep(Student $rep): array
    {
        if (! $rep->isRep()) {
            return [];
        }

        $classIds = $rep->repManagedClassIds();
        if ($classIds->isEmpty()) {
            return [];
        }

        $key = 'rep_flagged_v1:'.$classIds->sort()->values()->implode(',');

        return Cache::remember(
            $key,
            self::CACHE_SECONDS,
            fn () => app(AttendanceInsightsService::class)->flaggedStudents($classIds, self::THRESHOLD)
        );
    }

    public static function countForRep(Student $rep): int
    {
        return count(self::forRep($rep));
    }

    /**
     * @return array<int, true> student_id => true
     */
    public static function idMapForRep(Student $rep): array
    {
        $map = [];
        foreach (self::forRep($rep) as $row) {
            $map[(int) $row['student_id']] = true;
        }

        return $map;
    }
}
