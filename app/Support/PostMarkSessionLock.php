<?php

namespace App\Support;

use App\Models\AttendanceSession;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * After a student marks attendance on a shared device, block the same
 * student from signing in again until that session ends.
 */
class PostMarkSessionLock
{
    private const CACHE_PREFIX = 'student_post_mark_lock:';

    public static function lock(Student $student, AttendanceSession $session): void
    {
        $until = self::lockUntil($session);
        Cache::put(self::cacheKey($student->id), [
            'session_id' => (int) $session->id,
            'course_name' => $session->course?->course_name,
            'until' => $until->timestamp,
        ], $until);
    }

    /**
     * @return array{session_id: int, course_name: ?string, until: int}|null
     */
    public static function activeLock(Student $student): ?array
    {
        $data = Cache::get(self::cacheKey($student->id));
        if (! is_array($data)) {
            return null;
        }

        $until = (int) ($data['until'] ?? 0);
        if ($until <= time()) {
            Cache::forget(self::cacheKey($student->id));

            return null;
        }

        return $data;
    }

    public static function isLocked(Student $student): bool
    {
        return self::activeLock($student) !== null;
    }

    /**
     * @param  array{session_id?: int, course_name?: ?string, until?: int}  $lock
     */
    public static function blockMessage(?array $lock = null): string
    {
        $name = is_array($lock) ? trim((string) ($lock['course_name'] ?? '')) : '';

        if ($name !== '') {
            return 'You already marked attendance for '.$name.'. Sign in again after the class ends.';
        }

        return 'You already marked attendance for this class. Sign in again after the session ends.';
    }

    private static function cacheKey(int $studentId): string
    {
        return self::CACHE_PREFIX.$studentId;
    }

    private static function lockUntil(AttendanceSession $session): Carbon
    {
        foreach ([$session->end_time, $session->expires_at, $session->expected_end_time] as $t) {
            if ($t instanceof Carbon && $t->isFuture()) {
                return $t;
            }
        }

        return now()->addHours(3);
    }
}
