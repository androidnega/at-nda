<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Services\ClassSessionScopeService;
use App\Services\FcmNotificationService;
use App\Support\SessionQrPng;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSessionController extends Controller
{
    public function index(): View
    {
        $courses = Course::with('attendanceSessions')->latest()->get();

        return view('admin.portal', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'mode' => 'required|in:location,qr,hybrid,wifi',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'location_lat' => 'nullable|numeric',
            'location_lng' => 'nullable|numeric',
            'attendance_range_m' => 'nullable|integer|min:1|max:500',
            'allowed_wifi_ssid' => 'required_if:mode,wifi|nullable|string|max:128',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $primaryClassId = $course->class_id ? (int) $course->class_id : ($course->assignedClassIds()[0] ?? null);

        if (! $course->hasScheduleForClass($primaryClassId)) {
            return redirect()->route('dashboard.portal')->with('error', 'Set day and time for this course first.');
        }

        $needsAnchor = in_array($validated['mode'], ['location', 'hybrid'], true);
        $lat = $validated['location_lat'] ?? null;
        $lng = $validated['location_lng'] ?? null;
        $range = $validated['attendance_range_m'] ?? null;
        if ($needsAnchor) {
            if (($lat === null || $lng === null || $range === null) && $course->hasDefaultSessionLocation()) {
                $lat = $course->location_lat;
                $lng = $course->location_lng;
                $range = $course->attendance_range_m;
            }
            if ($lat === null || $lng === null || $range === null) {
                return redirect()->route('dashboard.portal')->with('error', 'Set a default location on the course or provide coordinates for location / hybrid sessions.');
            }
        }

        $wifiSsid = isset($validated['allowed_wifi_ssid']) ? trim((string) $validated['allowed_wifi_ssid']) : null;

        ClassSessionScopeService::deactivateActiveSessionsForClass(
            $primaryClassId ? (int) $primaryClassId : null,
            (int) $course->id
        );

        $week = $course->createOrGetAttendanceWeekForToday($primaryClassId);

        $duration = (int) ($validated['duration_minutes'] ?? 60);
        $expiresAt = $course->computeSessionExpiresAt($duration, $primaryClassId);
        $snapshot = \App\Support\ClassTimetableAccess::resolveScheduleSnapshot($course, $primaryClassId);
        $sessionModel = AttendanceSession::create([
            'course_id' => $course->id,
            'class_id' => $primaryClassId,
            'session_index' => AttendanceSession::nextIndexForCourse($course->id),
            'attendance_week_id' => $week->id,
            'mode' => $validated['mode'],
            'allowed_wifi_ssid' => $validated['mode'] === 'wifi' ? $wifiSsid : null,
            'is_active' => true,
            'lecturer_id' => $snapshot['lecturer_id'] ?? $course->lecturer_id,
            'venue_id' => $snapshot['venue_id'] ?? $course->venue_id,
            'start_time' => now(),
            'end_time' => $expiresAt,
            'expires_at' => $expiresAt,
            'location_lat' => $needsAnchor ? $lat : null,
            'location_lng' => $needsAnchor ? $lng : null,
            'attendance_range_m' => $needsAnchor ? $range : null,
        ]);

        ClassSessionScopeService::autoMarkClassRepsForSession($sessionModel, $course, $primaryClassId);

        app(FcmNotificationService::class)->sendSessionStartedToClass($course, $primaryClassId);

        $activeMinutes = max(1, (int) ceil(($expiresAt->getTimestamp() - now()->getTimestamp()) / 60));

        return redirect()->route('dashboard.portal')->with('success', 'Session opened. Week ' . $week->week_number . '. Active for ~' . $activeMinutes . ' min.');
    }

    public function close(AttendanceSession $session): RedirectResponse
    {
        $session->update(['is_active' => false]);

        return redirect()->route('dashboard.portal')->with('success', 'Session closed.');
    }

    public function qr(AttendanceSession $session)
    {
        if (!$session->isValid()) {
            return redirect()->route('dashboard.portal')->with('error', 'Session expired');
        }

        $payload = json_encode($session->getQrPayload());
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($payload);

        return view('admin.qr-display', compact('session', 'qrUrl'));
    }

    public function qrDownload(AttendanceSession $session): StreamedResponse|RedirectResponse
    {
        if (! $session->isValid()) {
            return redirect()->route('dashboard.portal')->with('error', 'Session expired');
        }

        $session->loadMissing('course');
        $course = $session->course;
        $payload = json_encode($session->getQrPayload());
        $png = SessionQrPng::fetchBytes($payload);
        $slug = Str::slug($course?->course_code ?: $course?->course_name ?: 'session');
        $filename = 'attendance-qr-'.$session->id.'-'.$slug.'.png';

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, $filename, [
            'Content-Type' => 'image/png',
        ]);
    }
}
