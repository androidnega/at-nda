<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCallLogsRequest;
use App\Http\Requests\Api\StoreSmsLogsRequest;
use App\Http\Requests\Api\StoreWhatsappLogsRequest;
use App\Models\Student;
use App\Services\Communications\CommunicationLogIngestService;
use App\Services\Communications\LoggingDisabledException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Mobile ingestion for SMS/call logs (Sanctum student + consent + institutional toggle).
 */
class CommunicationLogController extends Controller
{
    public function __construct(
        private readonly CommunicationLogIngestService $ingest
    ) {}

    public function storeSms(StoreSmsLogsRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if (! $this->ingest->isLoggingEnabled()) {
            return $this->loggingDisabledResponse();
        }

        try {
            $result = $this->ingest->ingestSms(
                $student,
                $request->validated('device_id'),
                $request->validated('items'),
                $request->validated('consent_version'),
            );
        } catch (LoggingDisabledException) {
            return $this->loggingDisabledResponse();
        } catch (\Throwable $e) {
            Log::error('communication.sms.api_failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not save SMS logs. Please try again later.',
                'error_code' => 'ingest_failed',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'SMS logs processed.',
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'errors' => $result['errors'],
        ]);
    }

    public function storeCalls(StoreCallLogsRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if (! $this->ingest->isLoggingEnabled()) {
            return $this->loggingDisabledResponse();
        }

        try {
            $result = $this->ingest->ingestCalls(
                $student,
                $request->validated('device_id'),
                $request->validated('items'),
                $request->validated('consent_version'),
            );
        } catch (LoggingDisabledException) {
            return $this->loggingDisabledResponse();
        } catch (\Throwable $e) {
            Log::error('communication.call.api_failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not save call logs. Please try again later.',
                'error_code' => 'ingest_failed',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Call logs processed.',
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'errors' => $result['errors'],
        ]);
    }

    public function storeWhatsapp(StoreWhatsappLogsRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if (! $this->ingest->isLoggingEnabled()) {
            return $this->loggingDisabledResponse();
        }

        try {
            $result = $this->ingest->ingestWhatsapp(
                $student,
                $request->validated('device_id'),
                $request->validated('items'),
                $request->validated('consent_version'),
            );
        } catch (LoggingDisabledException) {
            return $this->loggingDisabledResponse();
        } catch (\Throwable $e) {
            Log::error('communication.whatsapp.api_failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not save WhatsApp logs. Please try again later.',
                'error_code' => 'ingest_failed',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp logs processed.',
            'accepted' => $result['accepted'],
            'duplicates' => $result['duplicates'],
            'errors' => $result['errors'],
        ]);
    }

    private function loggingDisabledResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'SMS and call logging is disabled by the institution.',
            'error_code' => 'logging_disabled',
        ], 403);
    }
}
