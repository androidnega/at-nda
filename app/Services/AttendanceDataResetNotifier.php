<?php

namespace App\Services;

use App\Events\AttendanceDataResetEvent;
use App\Models\Course;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bump global sync version, broadcast, FCM, and bust settings cache after admin purges attendance data.
 */
class AttendanceDataResetNotifier
{
    /**
     * @param  array<int, int|string>  $courseIds
     */
    public static function notify(array $courseIds, string $scope): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        if ($courseIds === []) {
            return;
        }

        $classIds = Course::query()
            ->whereIn('id', $courseIds)
            ->pluck('class_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $settings = SystemSetting::get();
        $settings->increment('attendance_data_version');
        $settings->update(['last_attendance_reset_at' => now()]);
        $settings->refresh();

        $version = (int) $settings->attendance_data_version;

        event(new AttendanceDataResetEvent($courseIds, $classIds, $version, $scope));

        app(FcmNotificationService::class)->sendAttendanceDataReset($classIds, $version, $scope);

        Cache::forget('api_v1_settings');

        Log::info('attendance_data_reset', [
            'scope' => $scope,
            'version' => $version,
            'course_ids' => $courseIds,
            'class_ids' => $classIds,
        ]);
    }
}
