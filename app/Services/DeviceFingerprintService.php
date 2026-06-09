<?php

namespace App\Services;

use App\Models\AttendanceDeviceLog;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Persist the per-submission telemetry an online-attendance student
 * device reports.
 *
 * Client side: an open-source FingerprintJS bundle (loaded from CDN in
 * the online attendance view) computes a stable 32-char visitor id; the
 * Blade view also posts the rest of window.navigator (screen, timezone,
 * memory, cores, touch support) so the server doesn't have to guess.
 *
 * Server side: this service NEVER blocks attendance. Failures are logged
 * and swallowed. Caller proceeds as if telemetry was missing.
 */
class DeviceFingerprintService
{
    /**
     * Pull device-info fields from the request body and persist them.
     *
     * Returns the persisted row (or null on schema-missing / failure)
     * so AttendanceRiskService can pass it straight into scoring.
     *
     * @param  array<string, mixed>  $clientPayload  Optional pre-validated client data
     */
    public function record(
        Request $request,
        Student $student,
        AttendanceSession $session,
        array $clientPayload = []
    ): ?AttendanceDeviceLog {
        if (! SchemaFeatures::hasAttendanceDeviceLogs()) {
            return null;
        }

        try {
            $payload = $this->normalise($request, $clientPayload);

            return AttendanceDeviceLog::create(array_merge($payload, [
                'student_id' => $student->id,
                'session_id' => $session->id,
            ]));
        } catch (\Throwable $e) {
            Log::warning('[device-log] persist failed (non-blocking)', [
                'student_id' => $student->id,
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Coerce / clip the incoming payload to the column shapes. Defensive
     * against malicious oversize inputs (mb_substr caps).
     *
     * @param  array<string, mixed>  $clientPayload
     * @return array<string, mixed>
     */
    private function normalise(Request $request, array $clientPayload): array
    {
        $client = $clientPayload !== [] ? $clientPayload : (array) $request->input('client', []);

        $stringField = function ($value, int $max) {
            $value = is_scalar($value) ? trim((string) $value) : '';

            return $value === '' ? null : mb_substr($value, 0, $max);
        };

        $intField = function ($value) {
            if ($value === null || $value === '') {
                return null;
            }
            if (! is_numeric($value)) {
                return null;
            }
            $n = (int) $value;

            return ($n >= 0 && $n <= 65535) ? $n : null;
        };

        $boolField = function ($value) {
            if ($value === null || $value === '') {
                return null;
            }
            if (is_bool($value)) {
                return $value;
            }

            return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
        };

        return [
            'fingerprint_hash'  => $stringField($client['fingerprint_hash'] ?? null, 64),
            'ip_address'        => $stringField($request->ip(), 45),
            'user_agent'        => $stringField($request->userAgent(), 480),
            'platform'          => $stringField($client['platform'] ?? null, 80),
            'browser'           => $stringField($client['browser'] ?? null, 80),
            'operating_system'  => $stringField($client['operating_system'] ?? null, 80),
            'screen_resolution' => $stringField($client['screen_resolution'] ?? null, 32),
            'timezone'          => $stringField($client['timezone'] ?? null, 64),
            'language'          => $stringField($client['language'] ?? null, 16),
            'device_memory'     => $intField($client['device_memory'] ?? null),
            'cpu_cores'         => $intField($client['cpu_cores'] ?? null),
            'touch_support'     => $boolField($client['touch_support'] ?? null),
        ];
    }
}
