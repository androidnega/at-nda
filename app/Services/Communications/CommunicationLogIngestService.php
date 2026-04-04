<?php

namespace App\Services\Communications;

use App\Models\CallLog;
use App\Models\LoggedSms;
use App\Models\Student;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validates consent + feature flag, then persists SMS/call batches idempotently.
 */
final class CommunicationLogIngestService
{
    public function isLoggingEnabled(): bool
    {
        if (! SystemSetting::hasSmsCallLoggingColumn()) {
            return false;
        }

        return (bool) SystemSetting::get()->enable_sms_call_logging;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{accepted: int, duplicates: int, errors: list<array{client_record_id: string, message: string}>}
     */
    public function ingestSms(Student $student, string $deviceId, array $items, ?string $consentVersion): array
    {
        if (! $this->isLoggingEnabled()) {
            throw new LoggingDisabledException;
        }

        $indexNumber = strtoupper(trim((string) $student->index_number));
        $accepted = 0;
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use ($student, $deviceId, $items, $consentVersion, $indexNumber, &$accepted, &$duplicates, &$errors): void {
            foreach ($items as $row) {
                $clientId = (string) ($row['client_record_id'] ?? '');
                try {
                    $dup = LoggedSms::query()
                        ->where('index_number', $indexNumber)
                        ->where('device_id', $deviceId)
                        ->where('client_record_id', $clientId)
                        ->exists();
                    if ($dup) {
                        $duplicates++;

                        continue;
                    }
                    LoggedSms::query()->create([
                        'student_id' => $student->id,
                        'index_number' => $indexNumber,
                        'device_id' => $deviceId,
                        'client_record_id' => $clientId,
                        'direction' => $row['direction'],
                        'delivery_status' => $row['delivery_status'] ?? 'unknown',
                        'peer_number' => $row['peer_number'] ?? null,
                        'body_preview' => $row['body_preview'] ?? null,
                        'occurred_at' => Carbon::parse($row['occurred_at']),
                        'consent_version' => $consentVersion,
                    ]);
                    $accepted++;
                } catch (Throwable $e) {
                    Log::warning('communication.sms.ingest_row_failed', [
                        'student_id' => $student->id,
                        'client_record_id' => $clientId,
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = [
                        'client_record_id' => $clientId,
                        'message' => 'Could not store this row.',
                    ];
                }
            }
        });

        Log::info('communication.sms.batch', [
            'student_id' => $student->id,
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'error_rows' => count($errors),
        ]);

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{accepted: int, duplicates: int, errors: list<array{client_record_id: string, message: string}>}
     */
    public function ingestCalls(Student $student, string $deviceId, array $items, ?string $consentVersion): array
    {
        if (! $this->isLoggingEnabled()) {
            throw new LoggingDisabledException;
        }

        $indexNumber = strtoupper(trim((string) $student->index_number));
        $accepted = 0;
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use ($student, $deviceId, $items, $consentVersion, $indexNumber, &$accepted, &$duplicates, &$errors): void {
            foreach ($items as $row) {
                $clientId = (string) ($row['client_record_id'] ?? '');
                try {
                    $dup = CallLog::query()
                        ->where('index_number', $indexNumber)
                        ->where('device_id', $deviceId)
                        ->where('client_record_id', $clientId)
                        ->exists();
                    if ($dup) {
                        $duplicates++;

                        continue;
                    }
                    $endedAt = isset($row['ended_at']) ? Carbon::parse($row['ended_at']) : null;
                    CallLog::query()->create([
                        'student_id' => $student->id,
                        'index_number' => $indexNumber,
                        'device_id' => $deviceId,
                        'client_record_id' => $clientId,
                        'direction' => $row['direction'],
                        'call_outcome' => $row['call_outcome'] ?? 'unknown',
                        'duration_seconds' => isset($row['duration_seconds']) ? (int) $row['duration_seconds'] : null,
                        'peer_number' => $row['peer_number'] ?? null,
                        'occurred_at' => Carbon::parse($row['occurred_at']),
                        'ended_at' => $endedAt,
                        'consent_version' => $consentVersion,
                    ]);
                    $accepted++;
                } catch (Throwable $e) {
                    Log::warning('communication.call.ingest_row_failed', [
                        'student_id' => $student->id,
                        'client_record_id' => $clientId,
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = [
                        'client_record_id' => $clientId,
                        'message' => 'Could not store this row.',
                    ];
                }
            }
        });

        Log::info('communication.call.batch', [
            'student_id' => $student->id,
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'error_rows' => count($errors),
        ]);

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }
}
