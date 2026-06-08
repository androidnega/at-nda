<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\Lecturer;
use App\Services\AuditLogService;
use App\Support\LecturerAccess;
use App\Support\SchemaFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LecturerAttendanceWeekController extends Controller
{
    public function cancel(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        if (! $lecturer) {
            return redirect()->route('lecturer.login');
        }
        if ((int) $course->lecturer_id !== (int) $lecturer->id) {
            abort(403, 'This course is not assigned to you.');
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $attendanceWeek->update([
            'cancelled_at' => now(),
            'cancelled_by' => 'lecturer',
            'cancellation_note' => $validated['note'] ?? null,
        ]);

        AttendanceSession::query()
            ->where('attendance_week_id', $attendanceWeek->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return back()->with('success', 'Week '.$attendanceWeek->week_number.' marked as cancelled.');
    }

    public function uncancel(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        if (! $lecturer) {
            return redirect()->route('lecturer.login');
        }
        if ((int) $course->lecturer_id !== (int) $lecturer->id) {
            abort(403, 'This course is not assigned to you.');
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }

        $attendanceWeek->update([
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_note' => null,
        ]);

        return back()->with('success', 'Week '.$attendanceWeek->week_number.' cancellation cleared.');
    }

    /**
     * Bulk roll-call entry point for an online lecture week.
     *
     * The lecturer ticks Present / Absent / Late for each enrolled student
     * (along with an audit note + optional platform name). We:
     *   - find or create an attendance_sessions row anchored to the week
     *     (mode = 'online'); this keeps existing aggregations / FK chains
     *     working without inventing a parallel data path,
     *   - upsert one attendance row per student against that session,
     *     stamping `marked_manually_by_lecturer_id` and `manual_reason`
     *     so reports can tell self-marks from staff-entered marks,
     *   - optionally flag the week itself as `is_online` so reports / the
     *     weekly grid show an "Online" badge.
     *
     * Same validation pattern as ClassRepController::manualMarkAttendance,
     * but accepts the whole class in one POST instead of one student at a
     * time.
     */
    public function rollCall(Request $request, Course $course, AttendanceWeek $attendanceWeek): RedirectResponse
    {
        $debugId = bin2hex(random_bytes(4));
        Log::info('[ROLL-CALL] request.received', [
            'debug_id' => $debugId,
            'course_id' => (int) $course->id,
            'week_id' => (int) $attendanceWeek->id,
            'mark_count' => is_array($request->input('marks')) ? count($request->input('marks')) : 0,
        ]);

        $lecturer = $this->lecturer($request);
        if (! $lecturer) {
            return redirect()->route('lecturer.login');
        }
        if (! LecturerAccess::canManageCourse($lecturer, $course)) {
            abort(403, 'This course is not assigned to you.');
        }
        if ((int) $attendanceWeek->course_id !== (int) $course->id) {
            abort(404);
        }
        if ($attendanceWeek->isCancelled()) {
            return back()->with('error', 'Clear the cancellation on this week before running a roll-call.');
        }

        try {
            $validated = $request->validate([
                'note' => 'required|string|min:3|max:500',
                'platform' => 'nullable|string|max:60',
                'mark_online' => 'nullable|boolean',
                'marks' => 'required|array|min:1',
                'marks.*' => 'in:present,late,absent,skip',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[ROLL-CALL] validation.failed', [
                'debug_id' => $debugId,
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        // Enrolled student ids for this course's class(es). Anything outside
        // this set is ignored so a tampered form payload can't backdoor a
        // mark for a student in another class.
        $enrolledIds = $course->studentsQuery()->pluck('students.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $enrolledSet = array_flip($enrolledIds);

        // Find an existing session for this week (rep may have opened one
        // already); otherwise mint an "online" session that won't show up
        // in live-session lists because `is_active` stays false.
        $session = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->where('attendance_week_id', $attendanceWeek->id)
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            $anchorDate = $attendanceWeek->week_date ?? now();
            $attrs = [
                'course_id' => $course->id,
                'attendance_week_id' => $attendanceWeek->id,
                'mode' => 'online',
                'is_active' => false,
                'lecturer_id' => $lecturer->id,
                'venue_id' => $course->venue_id,
                'start_time' => $anchorDate,
                'end_time' => $anchorDate,
                'lecturer_status' => 'in_class',
            ];
            // Anchor to the course's primary class when the schema supports
            // it. Courses bound to multiple classes via the pivot fall back
            // to null which is intentional (the session covers the course,
            // not a single class) — matches the existing pivot handling in
            // openOrReopenForClass().
            if (SchemaFeatures::hasAttendanceSessionsClassId() && $course->class_id) {
                $attrs['class_id'] = (int) $course->class_id;
            }
            $session = AttendanceSession::create($attrs);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $platform = isset($validated['platform']) ? trim((string) $validated['platform']) : '';
        $platform = $platform === '' ? null : $platform;
        $reason = $platform !== null
            ? ('Online ('.$platform.') — '.$validated['note'])
            : ('Online — '.$validated['note']);

        DB::transaction(function () use (
            $validated,
            $enrolledSet,
            $session,
            $course,
            $attendanceWeek,
            $lecturer,
            $reason,
            $request,
            &$created,
            &$updated,
            &$skipped,
        ) {
            foreach ($validated['marks'] as $studentId => $status) {
                $sid = (int) $studentId;
                if (! isset($enrolledSet[$sid])) {
                    $skipped++;

                    continue;
                }
                if ($status === 'skip') {
                    $skipped++;

                    continue;
                }

                $row = Attendance::query()
                    ->where('student_id', $sid)
                    ->where('attendance_session_id', $session->id)
                    ->first();

                $payload = [
                    'status' => $status,
                    'manual_reason' => mb_substr($reason, 0, 500),
                    'marked_manually_at' => now(),
                    'marked_manually_by_lecturer_id' => (int) $lecturer->id,
                    'device_ip' => (string) $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
                ];

                if ($row) {
                    $row->update($payload);
                    $updated++;
                } else {
                    Attendance::create(array_merge($payload, [
                        'student_id' => $sid,
                        'course_id' => $course->id,
                        'attendance_session_id' => $session->id,
                        'attendance_week_id' => $attendanceWeek->id,
                        'attendance_time' => now(),
                        'synced' => true,
                    ]));
                    $created++;
                }
            }
        });

        // Tag the week as "Online" so the weekly grid / reports can show a
        // badge. Best-effort: silently skipped on older deploys that haven't
        // run the 2026_06_08_080000 migration yet (SchemaFeatures guards it
        // through the model's saving() hook).
        $markOnline = (bool) ($validated['mark_online'] ?? true);
        if ($markOnline && SchemaFeatures::hasAttendanceWeeksOnlineFlag()) {
            $attendanceWeek->update([
                'is_online' => true,
                'online_platform' => $platform,
                'online_note' => mb_substr($validated['note'], 0, 500),
            ]);
        }

        AuditLogService::record(AuditLogService::MARK_MANUAL, [
            'request' => $request,
            'course_id' => (int) $course->id,
            'class_id' => $course->class_id ? (int) $course->class_id : null,
            'attendance_session_id' => (int) $session->id,
            'subject_type' => 'attendance_week',
            'subject_id' => (int) $attendanceWeek->id,
            'payload' => [
                'kind' => 'online_roll_call',
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'platform' => $platform,
                'note' => $validated['note'],
                'week_number' => $attendanceWeek->week_number,
                'marked_online' => $markOnline,
            ],
        ]);

        Log::info('[ROLL-CALL] success', [
            'debug_id' => $debugId,
            'session_id' => (int) $session->id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return back()->with(
            'success',
            sprintf(
                'Online roll-call saved for Week %d · %d added · %d updated · %d skipped.',
                $attendanceWeek->week_number,
                $created,
                $updated,
                $skipped
            )
        );
    }

    private function lecturer(Request $request): ?Lecturer
    {
        $id = $request->session()->get('lecturer_id');
        if (! $id) {
            return null;
        }

        return Lecturer::find($id);
    }
}
