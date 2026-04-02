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
        $attendanceToday = Attendance::whereDate('attendance_time', today())->count();
        $totalAttendances = Attendance::count();

        $attendances = Attendance::with(['student', 'course'])
            ->latest('attendance_time')
            ->paginate(20);

        // Last 7 days attendance trend
        $attendanceTrend = Attendance::selectRaw('DATE(attendance_time) as date, COUNT(*) as count')
            ->where('attendance_time', '>=', now()->subDays(6)->startOfDay())
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

        // Attendance by course (top 5)
        $attendanceByCourse = Course::withCount('attendances')
            ->orderByDesc('attendances_count')
            ->limit(5)
            ->get();

        // Top students by attendance count
        $topStudents = Student::withCount('attendances')
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
            ->latest('attendance_time')
            ->paginate(30);

        return view('admin.attendances', compact('attendances'));
    }
}
