<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Services\Api\ClassRepApiService;
use App\Support\ApiEnvelope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceRecordsController extends Controller
{
    public function __construct(
        private readonly ClassRepApiService $classRepApi,
    ) {}

    /**
     * GET /api/attendance/{session}/records
     */
    public function records(Request $request, AttendanceSession $session): JsonResponse
    {
        $student = $this->classRepApi->authenticateFlexible($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $course = $session->course()->first();
        if (! $course) {
            return ApiEnvelope::error('Course not found', 404);
        }

        $managed = $student->repManagedClassIds();
        if (! $course->class_id || ! $managed->contains((int) $course->class_id)) {
            return ApiEnvelope::error('Not allowed', 403);
        }

        $rows = $this->attendanceRowsForSession($session->id);

        return ApiEnvelope::success([
            'session_id' => $session->id,
            'course_id' => $course->id,
            'course_name' => $course->course_name,
            'course_code' => $course->course_code,
            'count' => $rows->count(),
            'records' => $rows->values()->all(),
        ], 'Attendance records loaded');
    }

    /**
     * GET /api/attendance/{session}/export/csv
     */
    public function exportCsv(Request $request, AttendanceSession $session): StreamedResponse|JsonResponse
    {
        $auth = $this->authorizeRepForSession($request, $session);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $course = $auth['course'];
        $rows = $this->attendanceRowsForSession($session->id);

        $filename = 'attendance-records-session-'.$session->id.'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Index number', 'Time marked']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['name'] ?? '',
                    $r['index_number'] ?? '',
                    $r['marked_at'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Course-Name' => (string) ($course->course_name ?? ''),
        ]);
    }

    /**
     * GET /api/attendance/{session}/export/excel
     */
    public function exportExcel(Request $request, AttendanceSession $session): BinaryFileResponse|JsonResponse
    {
        $auth = $this->authorizeRepForSession($request, $session);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $export = new \App\Exports\AttendanceSessionRecordsExport($session->id);

        return Excel::download($export, 'attendance-records-session-'.$session->id.'.xlsx');
    }

    /**
     * GET /api/attendance/{session}/export/pdf
     */
    public function exportPdf(Request $request, AttendanceSession $session): StreamedResponse|JsonResponse
    {
        $auth = $this->authorizeRepForSession($request, $session);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $course = $auth['course'];
        $rows = $this->attendanceRowsForSession($session->id)->values()->all();

        $pdf = Pdf::loadView('pdf.attendance-records', [
            'session' => $session,
            'course' => $course,
            'records' => $rows,
            'generated_at' => now(),
        ])->setPaper('a4');

        return $pdf->download('attendance-records-session-'.$session->id.'.pdf');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{name:string,index_number:string,marked_at:?string}>
     */
    private function attendanceRowsForSession(int $sessionId): Collection
    {
        return Attendance::query()
            ->with('student')
            ->where('attendance_session_id', $sessionId)
            ->orderBy('attendance_time')
            ->get()
            ->map(function (Attendance $a) {
                $s = $a->student;
                $name = $s?->getDisplayNameOrIndex() ?? '—';
                $idx = $s?->index_number ?? '';
                $markedAt = $a->attendance_time?->toIso8601String() ?? $a->created_at?->toIso8601String();

                return [
                    'name' => (string) $name,
                    'index_number' => (string) $idx,
                    'marked_at' => $markedAt,
                ];
            });
    }

    /**
     * @return array{course:\App\Models\Course}
     */
    private function authorizeRepForSession(Request $request, AttendanceSession $session): array|JsonResponse
    {
        $student = $this->classRepApi->authenticateFlexible($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $course = $session->course()->first();
        if (! $course) {
            return ApiEnvelope::error('Course not found', 404);
        }

        $managed = $student->repManagedClassIds();
        if (! $course->class_id || ! $managed->contains((int) $course->class_id)) {
            return ApiEnvelope::error('Not allowed', 403);
        }

        return ['course' => $course];
    }
}

