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

    /**
     * Create (or reuse) today's attendance week for the course and flag it
     * as an online lecture so the lecturer can immediately roll-call.
     *
     * Used when no week card exists yet because nobody opened a live
     * session — for a purely remote class that path is never taken, so
     * we skip the live-session step entirely and let the lecturer
     * declare a week up front.
     *
     * Redirects back to the course attendance page with `?focus_week=<id>`
     * so the view can auto-expand that week and open its roll-call form.
     */
    public function createOnlineWeek(Request $request, Course $course): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        if (! $lecturer) {
            return redirect()->route('lecturer.login');
        }
        if (! LecturerAccess::canManageCourse($lecturer, $course)) {
            abort(403, 'This course is not assigned to you.');
        }

        $validated = $request->validate([
            'platform' => 'nullable|string|max:60',
            'note' => 'nullable|string|max:500',
            'week_number' => 'nullable|integer|min:1|max:500',
        ]);

        $classId = $course->class_id ? (int) $course->class_id : null;
        $overrideWeekNumber = isset($validated['week_number']) ? (int) $validated['week_number'] : null;

        $week = $course->createOrGetAttendanceWeekForToday($classId, $overrideWeekNumber);

        $platform = isset($validated['platform']) ? trim((string) $validated['platform']) : '';
        $platform = $platform === '' ? null : $platform;
        $note = isset($validated['note']) ? mb_substr(trim((string) $validated['note']), 0, 500) : null;
        if ($note === '') {
            $note = null;
        }

        // Ensure an online session exists for this week. If a session of
        // any mode is already attached (e.g. the rep opened an in-person
        // session earlier today), we either upgrade it to 'online' when
        // it has zero marks (rare race), or mint a parallel inactive
        // online session so the week now carries the online signal
        // without rewriting in-person attendance.
        $onlineSession = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->where('attendance_week_id', $week->id)
            ->where('mode', 'online')
            ->orderByDesc('id')
            ->first();

        if (! $onlineSession) {
            $anchorDate = $week->week_date ?? now();
            $attrs = [
                'course_id' => $course->id,
                'attendance_week_id' => $week->id,
                'mode' => 'online',
                'is_active' => false,
                'lecturer_id' => $lecturer->id,
                'venue_id' => $course->venue_id,
                'start_time' => $anchorDate,
                'end_time' => $anchorDate,
                'lecturer_status' => 'in_class',
            ];
            if (SchemaFeatures::hasAttendanceSessionsClassId() && $classId) {
                $attrs['class_id'] = (int) $classId;
            }
            $onlineSession = AttendanceSession::create($attrs);
        }

        // Cache the platform / note on the week itself so the badge has
        // a label without joining to the session. This is the only
        // path (besides the rep open-session form with mode=online)
        // that flips the week's is_online flag — roll-call no longer
        // does it.
        if (SchemaFeatures::hasAttendanceWeeksOnlineFlag()) {
            $week->update([
                'is_online' => true,
                'online_platform' => $platform ?? $week->online_platform,
                'online_note' => $note ?? $week->online_note ?? 'Online lecture',
            ]);
        }

        AuditLogService::record(AuditLogService::SESSION_OPENED, [
            'request' => $request,
            'course_id' => (int) $course->id,
            'class_id' => $classId,
            'attendance_session_id' => (int) $onlineSession->id,
            'subject_type' => 'attendance_session',
            'subject_id' => (int) $onlineSession->id,
            'payload' => [
                'kind' => 'online_lecture',
                'week_number' => $week->week_number,
                'platform' => $platform,
            ],
        ]);

        return redirect()
            ->route('dashboard.teaching.attendance.course', ['course' => $course->id, 'focus_week' => $week->id])
            ->with('success', 'Week '.$week->week_number.' opened as an online lecture. Tick attendees in the roll-call.');
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
                // 'manual' is the catch-all mode for sessions minted by a
                // bare roll-call (no live session ever opened). It is
                // intentionally neither 'online' nor an anchor-requiring
                // mode so:
                //   - AttendanceWeek::isOnline() does not flip a badge on,
                //   - AttendanceSession::requiresLocationAnchor()/QrToken
                //     stay false (no anchor / no QR token expected),
                //   - the offline sync layer doesn't try to validate it
                //     as a Flutter-app session.
                'attendance_week_id' => $attendanceWeek->id,
                'mode' => 'manual',
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

        // NOTE: We deliberately do NOT flip the week's is_online flag
        // from here. The badge is now session-driven (set when an
        // online session is opened via openSession with mode='online'
        // or via createOnlineWeek). Running a roll-call against an
        // existing in-person session is legitimate — e.g. a rep
        // batch-marking after a power outage — and shouldn't relabel
        // the week as "online".

        AuditLogService::record(AuditLogService::MARK_MANUAL, [
            'request' => $request,
            'course_id' => (int) $course->id,
            'class_id' => $course->class_id ? (int) $course->class_id : null,
            'attendance_session_id' => (int) $session->id,
            'subject_type' => 'attendance_week',
            'subject_id' => (int) $attendanceWeek->id,
            'payload' => [
                'kind' => 'roll_call',
                'session_mode' => $session->mode,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'platform' => $platform,
                'note' => $validated['note'],
                'week_number' => $attendanceWeek->week_number,
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
