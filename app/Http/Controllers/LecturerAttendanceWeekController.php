<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\AttendanceWeek;
use App\Models\Course;
use App\Models\Lecturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    private function lecturer(Request $request): ?Lecturer
    {
        $id = $request->session()->get('lecturer_id');
        if (! $id) {
            return null;
        }

        return Lecturer::find($id);
    }
}
