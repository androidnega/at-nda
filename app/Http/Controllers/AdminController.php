<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Student;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $totalStudents = Student::count();
        $totalCourses = Course::count();
        // Counts ignore attendance rows whose backing week was cancelled
        // or already reset — those still live in the DB for audit but
        // shouldn't inflate the headline dashboard numbers.
        $attendanceToday = Attendance::query()
            ->whereDate('attendance_time', today())
            ->activeWeeksOnly()
            ->count();
        $totalAttendances = Attendance::query()->activeWeeksOnly()->count();

        $attendances = Attendance::with(['student', 'course'])
            ->activeWeeksOnly()
            ->latest('attendance_time')
            ->paginate(20);

        // Last 7 days attendance trend
        $attendanceTrend = Attendance::selectRaw('DATE(attendance_time) as date, COUNT(*) as count')
            ->where('attendance_time', '>=', now()->subDays(6)->startOfDay())
            ->activeWeeksOnly()
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $last7Days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $last7Days->push([
                'date' => $d,
                'label' => now()->subDays($i)->format('D'),
                'count' => $attendanceTrend[$d] ?? 0,
            ]);
        }

        // Attendance by course (top 5) — count only marks on active weeks.
        $attendanceByCourse = Course::withCount([
                'attendances' => fn ($q) => $q->activeWeeksOnly(),
            ])
            ->orderByDesc('attendances_count')
            ->limit(5)
            ->get();

        // Top students by attendance count — same active-week filter.
        $topStudents = Student::withCount([
                'attendances' => fn ($q) => $q->activeWeeksOnly(),
            ])
            ->orderByDesc('attendances_count')
            ->limit(5)
            ->get();

        return view('admin.dashboard', array_merge(compact(
            'attendances',
            'totalStudents',
            'totalCourses',
            'attendanceToday',
            'totalAttendances',
            'last7Days',
            'attendanceByCourse',
            'topStudents'
        ), ['dashboardRole' => 'admin']));
    }

    public function attendances(): View
    {
        $attendances = Attendance::with(['student', 'course'])
            ->activeWeeksOnly()
            ->latest('attendance_time')
            ->paginate(30);

        return view('admin.attendances', compact('attendances'));
    }
}
