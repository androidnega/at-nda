<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    /**
     * Admin landing dashboard. Everything on this page is now driven by
     * a single ?period= filter (today | week | month | all) so the UI
     * date-range pill stays in sync with every tile and chart.
     */
    public function dashboard(Request $request): View
    {
        $period = $this->resolvePeriod($request->query('period'));
        [$periodStart, $periodEnd] = $this->periodWindow($period);

        $totalStudents = Student::count();
        $totalCourses = Course::count();

        // Headline counters. `attendanceToday` stays calendar-day-based so
        // the "Today" tile is consistent regardless of the active period;
        // every other counter respects the chosen period window.
        $attendanceToday = Attendance::query()
            ->whereDate('attendance_time', today())
            ->activeWeeksOnly()
            ->count();

        $periodAttendances = Attendance::query()
            ->when($periodStart, fn ($q) => $q->where('attendance_time', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('attendance_time', '<=', $periodEnd))
            ->activeWeeksOnly()
            ->count();

        $totalAttendances = Attendance::query()->activeWeeksOnly()->count();

        // Live right now — sessions whose start_time/end_time still bracket
        // the current instant. Drives the "Live sessions" tile + chip.
        $liveSessionsCount = AttendanceSession::query()
            ->activeWithinTimeWindow()
            ->count();

        // 7-day trend (always shown; not tied to period filter so the
        // mini-chart keeps its rhythm even when the user picks a longer
        // window).
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

        // Top courses by attendance count for the chosen period.
        $attendanceByCourse = Course::query()
            ->withCount([
                'attendances' => function ($q) use ($periodStart, $periodEnd) {
                    $q->activeWeeksOnly();
                    if ($periodStart) {
                        $q->where('attendance_time', '>=', $periodStart);
                    }
                    if ($periodEnd) {
                        $q->where('attendance_time', '<=', $periodEnd);
                    }
                },
            ])
            ->orderByDesc('attendances_count')
            ->limit(6)
            ->get();

        // Top students for the chosen period.
        $topStudents = Student::query()
            ->withCount([
                'attendances' => function ($q) use ($periodStart, $periodEnd) {
                    $q->activeWeeksOnly();
                    if ($periodStart) {
                        $q->where('attendance_time', '>=', $periodStart);
                    }
                    if ($periodEnd) {
                        $q->where('attendance_time', '<=', $periodEnd);
                    }
                },
            ])
            ->orderByDesc('attendances_count')
            ->limit(8)
            ->get();

        // Mode breakdown — count marks grouped by their session mode so
        // the operator can see which capture method is dominant. Falls
        // back gracefully when a mark predates the session/mode link.
        $attendanceByMode = Attendance::query()
            ->activeWeeksOnly()
            ->when($periodStart, fn ($q) => $q->where('attendance_time', '>=', $periodStart))
            ->when($periodEnd, fn ($q) => $q->where('attendance_time', '<=', $periodEnd))
            ->leftJoin('attendance_sessions', 'attendances.attendance_session_id', '=', 'attendance_sessions.id')
            ->selectRaw('COALESCE(attendance_sessions.mode, "location") as mode, COUNT(*) as total')
            ->groupBy('mode')
            ->pluck('total', 'mode')
            ->toArray();

        $modes = ['location', 'qr', 'hybrid', 'wifi'];
        $modeBreakdown = collect($modes)->map(fn ($m) => [
            'mode' => $m,
            'label' => match ($m) {
                'qr' => 'QR code',
                'hybrid' => 'Hybrid',
                'wifi' => 'Wi-Fi',
                default => 'Location',
            },
            'count' => (int) ($attendanceByMode[$m] ?? 0),
        ]);
        $totalModeCount = max($modeBreakdown->sum('count'), 1);

        // Students-per-faculty breakdown — only when the faculties table
        // is wired up. Walks the students→departments→faculties chain
        // with a single grouped join (cheaper than N+1 withCount calls
        // and survives the case where a department has no faculty link).
        $facultyBreakdown = collect();
        try {
            if (Schema::hasTable('faculties') && Schema::hasTable('departments') && Schema::hasColumn('students', 'department_id')) {
                $facultyBreakdown = DB::table('faculties')
                    ->join('departments', 'departments.faculty_id', '=', 'faculties.id')
                    ->join('students', 'students.department_id', '=', 'departments.id')
                    ->select('faculties.id', 'faculties.name', DB::raw('COUNT(students.id) as cnt'))
                    ->groupBy('faculties.id', 'faculties.name')
                    ->orderByDesc('cnt')
                    ->limit(5)
                    ->get()
                    ->map(fn ($r) => [
                        'name' => (string) $r->name,
                        'count' => (int) $r->cnt,
                    ])
                    ->values();
            }
        } catch (\Throwable $e) {
            report($e);
            $facultyBreakdown = collect();
        }

        // Recent activity — last 8 marks, with student + course already
        // eager-loaded for the feed renderer.
        $recentActivity = Attendance::query()
            ->with(['student:id,index_number,first_name,last_name', 'course:id,course_name,course_code'])
            ->activeWeeksOnly()
            ->latest('attendance_time')
            ->limit(8)
            ->get();

        // Recent audit events (rep/admin actions) — pull from the audit
        // log so admins can see who created sessions, extended class
        // time, or closed sessions without leaving the dashboard.
        $recentAudit = collect();
        try {
            if (Schema::hasTable('audit_logs')) {
                $recentAudit = AuditLog::query()
                    ->latest('id')
                    ->limit(6)
                    ->get(['id', 'actor_name', 'actor_role', 'action', 'created_at']);
            }
        } catch (\Throwable $e) {
            report($e);
            $recentAudit = collect();
        }

        // Average attendance rate for the period: how many marks land per
        // expected student-session (capped so it never exceeds 100%).
        $expectedMarks = $totalStudents > 0 && $periodAttendances > 0
            ? max($totalStudents, 1)
            : 1;
        $attendanceRate = min(100, $totalStudents > 0
            ? round(($periodAttendances / $expectedMarks) * 100)
            : 0);

        $periodLabel = match ($period) {
            'today' => 'Today',
            'week' => 'This week',
            'month' => 'This month',
            default => 'All-time',
        };

        return view('admin.dashboard', array_merge(compact(
            'totalStudents',
            'totalCourses',
            'attendanceToday',
            'totalAttendances',
            'periodAttendances',
            'liveSessionsCount',
            'last7Days',
            'attendanceByCourse',
            'topStudents',
            'modeBreakdown',
            'totalModeCount',
            'facultyBreakdown',
            'recentActivity',
            'recentAudit',
            'attendanceRate',
            'period',
            'periodLabel',
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

    /**
     * Admin review panel for attendance rows that AttendanceRiskService
     * flagged as suspicious.
     *
     * Strictly read-only — these rows ARE marked as present (per spec
     * PART 12, even HIGH risk doesn't block). The panel just surfaces
     * them so a human can investigate. Filterable by ?level=low|medium|high
     * and ?session=<id>; defaults to "medium and above" so the page
     * doesn't drown in LOW noise.
     */
    public function suspiciousAttendances(Request $request): View
    {
        $hasRiskColumns = \App\Support\SchemaFeatures::hasAttendancesRiskColumns();

        $level   = (string) $request->query('level', 'medium_plus');
        $session = (int) $request->query('session', 0);

        $query = Attendance::query()
            ->with(['student.schoolClass', 'course', 'attendanceSession']);

        if ($hasRiskColumns) {
            $query->whereNotNull('risk_level');
            if ($level === 'low') {
                $query->where('risk_level', 'low');
            } elseif ($level === 'medium') {
                $query->where('risk_level', 'medium');
            } elseif ($level === 'high') {
                $query->where('risk_level', 'high');
            } else {
                // Default: medium + high (skip noise).
                $query->whereIn('risk_level', ['medium', 'high']);
            }
            $query->orderByDesc('risk_score')
                ->orderByDesc('attendance_time');
        } else {
            // Schema hasn't picked up the migration yet — render an empty
            // list so the page never 500s on a stale deploy.
            $query->whereRaw('1 = 0');
        }

        if ($session > 0) {
            $query->where('attendance_session_id', $session);
        }

        $rows = $query->paginate(30)->appends($request->query());

        $counts = $hasRiskColumns
            ? Attendance::query()
                ->selectRaw("risk_level, COUNT(*) as c")
                ->whereNotNull('risk_level')
                ->groupBy('risk_level')
                ->pluck('c', 'risk_level')
                ->toArray()
            : [];

        return view('admin.suspicious-attendances', [
            'rows'           => $rows,
            'counts'         => $counts,
            'level'          => $level,
            'session'        => $session,
            'hasRiskColumns' => $hasRiskColumns,
        ]);
    }

    /**
     * Stream a CSV of attendance marks for the chosen period. Used by
     * the "Download" action on the admin dashboard so we don't have to
     * build a separate reporting page just for an export.
     */
    public function exportAttendance(Request $request): StreamedResponse
    {
        $period = $this->resolvePeriod($request->query('period'));
        [$periodStart, $periodEnd] = $this->periodWindow($period);
        $stamp = now()->format('Ymd-His');
        $filename = "attendance-{$period}-{$stamp}.csv";

        return response()->streamDownload(function () use ($periodStart, $periodEnd) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Recorded at',
                'Index number',
                'Student name',
                'Course code',
                'Course name',
                'Week',
                'Mode',
                'Status',
                'Latitude',
                'Longitude',
            ]);

            Attendance::query()
                ->with([
                    'student:id,index_number,first_name,last_name',
                    'course:id,course_name,course_code',
                    'session:id,mode',
                    'attendanceWeek:id,week_number',
                ])
                ->activeWeeksOnly()
                ->when($periodStart, fn ($q) => $q->where('attendance_time', '>=', $periodStart))
                ->when($periodEnd, fn ($q) => $q->where('attendance_time', '<=', $periodEnd))
                ->orderByDesc('attendance_time')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        $name = trim(($row->student?->first_name ?? '').' '.($row->student?->last_name ?? ''));
                        fputcsv($out, [
                            optional($row->attendance_time)->format('Y-m-d H:i:s'),
                            $row->student?->index_number ?? '',
                            $name,
                            $row->course?->course_code ?? '',
                            $row->course?->course_name ?? '',
                            $row->attendanceWeek?->week_number ?? '',
                            $row->session?->mode ?? '',
                            $row->status ?? '',
                            $row->lat ?? '',
                            $row->lng ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Whitelist + normalise the ?period= query value so a craft user
     * can't sneak SQL or surprise the period switch.
     */
    private function resolvePeriod(?string $raw): string
    {
        return in_array($raw, ['today', 'week', 'month', 'all'], true)
            ? $raw
            : 'month';
    }

    /**
     * @return array{0: \Carbon\Carbon|null, 1: \Carbon\Carbon|null}
     */
    private function periodWindow(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::tomorrow()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default => [null, null], // 'all'
        };
    }
}
