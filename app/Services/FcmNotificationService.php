<?php

namespace App\Services;

use App\Models\Course;
use App\Models\StudentDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    /**
     * Notify all students in the course's class that a session is active (legacy HTTP API).
     */
    public function sendSessionStartedToClass(Course $course): void
    {
        $key = config('services.fcm.server_key');
        if (empty($key)) {
            Log::debug('FCM: FCM_SERVER_KEY not set; skipping push');

            return;
        }

        if (!$course->class_id) {
            return;
        }

        $tokens = StudentDeviceToken::query()
            ->whereHas('student', fn ($q) => $q->where('class_id', $course->class_id))
            ->pluck('firebase_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $key,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => 'Attendance Started',
                    'body' => 'New session is active. Mark attendance now.',
                ],
            ]);

            if (!$response->successful()) {
                Log::warning('FCM batch failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }
    }

    /**
     * Send a direct lecturer message to specific students.
     *
     * @param  array<int, int>  $studentIds
     */
    public function sendDirectMessageToStudents(array $studentIds, string $title, string $body): void
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if ($studentIds === []) {
            return;
        }

        $key = config('services.fcm.server_key');
        if (empty($key)) {
            Log::debug('FCM: FCM_SERVER_KEY not set; skipping lecturer direct push');

            return;
        }

        $tokens = StudentDeviceToken::query()
            ->whereIn('student_id', $studentIds)
            ->pluck('firebase_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$key,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $chunk,
                'priority' => 'high',
                'content_available' => true,
                'data' => [
                    'kind' => 'lecturer_direct_message',
                ],
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('FCM direct lecturer batch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }
    }

    /**
     * Notify devices in affected classes that attendance data was reset (poll settings or resync).
     * Data payload is string-only (FCM legacy HTTP API requirement).
     *
     * @param  array<int, int>  $classIds
     */
    public function sendAttendanceDataReset(array $classIds, int $version, string $scope): void
    {
        $key = config('services.fcm.server_key');
        if (empty($key)) {
            Log::debug('FCM: FCM_SERVER_KEY not set; skipping attendance_data_reset push');

            return;
        }

        $tokens = [];
        if ($classIds !== []) {
            $tokens = StudentDeviceToken::query()
                ->whereHas('student', fn ($q) => $q->whereIn('class_id', $classIds))
                ->pluck('firebase_token')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        // Full system reset: still notify every registered device if class targeting found nobody.
        if ($tokens === [] && $scope === 'all') {
            $tokens = StudentDeviceToken::query()
                ->pluck('firebase_token')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($tokens === []) {
            Log::debug('FCM attendance_data_reset: no device tokens (class_ids empty and not scope=all, or no registrations)');

            return;
        }

        $v = (string) $version;
        $chunks = array_chunk($tokens, 500);
        foreach ($chunks as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$key,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $chunk,
                'priority' => 'high',
                'content_available' => true,
                'data' => [
                    'action' => 'attendance_data_reset',
                    'attendance_data_version' => $v,
                    'scope' => $scope,
                ],
                'notification' => [
                    'title' => 'Attendance data updated',
                    'body' => 'Your attendance history was refreshed on the server. Open the app to sync.',
                ],
            ]);

            if (!$response->successful()) {
                Log::warning('FCM attendance_data_reset batch failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }
    }
}
