<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Super-admin view: every audit event across the platform.
     */
    public function adminIndex(Request $request): View
    {
        $available = SchemaFeatures::hasAuditLogs();

        $query = AuditLog::query()->orderByDesc('id');

        if ($available) {
            if ($action = $request->query('action')) {
                $query->where('action', $action);
            }
            if ($actorRole = $request->query('role')) {
                $query->where('actor_role', $actorRole);
            }
            if ($classId = (int) $request->query('class_id')) {
                $query->where('class_id', $classId);
            }
            if ($search = trim((string) $request->query('search'))) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($q) use ($like) {
                    $q->where('actor_name', 'like', $like)
                        ->orWhere('ip', 'like', $like)
                        ->orWhere('action', 'like', $like);
                });
            }
            $logs = $query->paginate(40)->withQueryString();
        } else {
            $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 40);
        }

        // Resolve student / class / department / faculty per row so
        // the table can show an index number and the modal can show
        // "Faculty of … · Department of … · Class …" — without N
        // extra queries per row. Three batched lookups total.
        $studentMetaByLog = self::resolveStudentMetadata($logs->getCollection());

        return view('admin.audit-logs', [
            'logs' => $logs,
            'available' => $available,
            'actions' => self::knownActions(),
            'studentMetaByLog' => $studentMetaByLog,
        ]);
    }

    /**
     * Build a per-log map of student/department/class info, used by
     * the audit-log Blade partial. Performs at most:
     *   1× attendances IN (...) lookup,
     *   1× students    IN (...) lookup (with effective dept fallback),
     *   1× classes     IN (...) lookup (with faculty + department).
     *
     * Returns:
     *   [ $log->id => [
     *       'student_id', 'index_number', 'full_name',
     *       'class_id', 'class_name',
     *       'department_id', 'department_name',
     *       'faculty_id', 'faculty_name',
     *     ], ... ]
     *
     * @param  Collection<int, AuditLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private static function resolveStudentMetadata(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $attendanceIds = [];
        $studentIdsDirect = [];
        // Track which log each lookup belongs to so we can fan back
        // out after the batched queries.
        $logAttendanceId = [];   // [$log->id => attendance_id]
        $logStudentIdDirect = []; // [$log->id => student_id]
        $logIndexFromPayload = []; // [$log->id => index_number]

        foreach ($logs as $log) {
            $payload = is_array($log->payload) ? $log->payload : [];

            // Most rep-mark / fraud-detection rows have the
            // student_id already in the payload. Cheap to use it
            // directly — avoids the attendance round-trip.
            if (! empty($payload['student_id']) && is_numeric($payload['student_id'])) {
                $sid = (int) $payload['student_id'];
                $studentIdsDirect[] = $sid;
                $logStudentIdDirect[(int) $log->id] = $sid;
            }

            if (! empty($payload['index_number'])) {
                $logIndexFromPayload[(int) $log->id] = (string) $payload['index_number'];
            }

            $type = (string) ($log->subject_type ?? '');
            $sid = (int) ($log->subject_id ?? 0);
            if ($sid <= 0) {
                continue;
            }

            if ($type === 'attendance') {
                $attendanceIds[] = $sid;
                $logAttendanceId[(int) $log->id] = $sid;
            } elseif ($type === 'student') {
                $studentIdsDirect[] = $sid;
                if (! isset($logStudentIdDirect[(int) $log->id])) {
                    $logStudentIdDirect[(int) $log->id] = $sid;
                }
            }
        }

        // Resolve attendance → student_id.
        $studentIdByAttendance = [];
        if ($attendanceIds !== []) {
            $studentIdByAttendance = Attendance::query()
                ->whereIn('id', array_values(array_unique($attendanceIds)))
                ->pluck('student_id', 'id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        // Final pool of student ids to hydrate.
        $studentIds = array_unique(array_merge(
            array_map('intval', $studentIdsDirect),
            array_map('intval', array_values($studentIdByAttendance)),
        ));

        $students = collect();
        if ($studentIds !== []) {
            $students = Student::query()
                ->whereIn('id', $studentIds)
                ->get(['id', 'index_number', 'first_name', 'middle_name', 'last_name', 'class_id', 'department_id']);
        }
        $studentById = $students->keyBy(fn ($s) => (int) $s->id);

        // Pull class + faculty + department in one shot. Use the
        // class's department (the rep-controlled source of truth)
        // before falling back to the student's `department_id`.
        $classIds = $students
            ->pluck('class_id')
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
        $classById = collect();
        if ($classIds !== []) {
            $classById = SchoolClass::query()
                ->with(['department.faculty', 'faculty'])
                ->whereIn('id', $classIds)
                ->get()
                ->keyBy(fn ($c) => (int) $c->id);
        }

        $out = [];
        foreach ($logs as $log) {
            $logId = (int) $log->id;
            $studentId = $logStudentIdDirect[$logId] ?? null;
            if ($studentId === null && isset($logAttendanceId[$logId])) {
                $studentId = $studentIdByAttendance[$logAttendanceId[$logId]] ?? null;
            }

            // No student resolvable for this row — but we still
            // surface the payload-provided index if any (existing
            // behaviour for mark_manual rows).
            if ($studentId === null) {
                if (isset($logIndexFromPayload[$logId])) {
                    $out[$logId] = [
                        'student_id' => null,
                        'index_number' => $logIndexFromPayload[$logId],
                        'full_name' => null,
                        'class_id' => null,
                        'class_name' => null,
                        'department_id' => null,
                        'department_name' => null,
                        'faculty_id' => null,
                        'faculty_name' => null,
                    ];
                }

                continue;
            }

            $student = $studentById->get((int) $studentId);
            if (! $student) {
                continue;
            }

            $class = $student->class_id ? $classById->get((int) $student->class_id) : null;

            // Prefer the class-derived department (source of truth);
            // fall back to the student's own department_id.
            $dept = $class?->department;
            $faculty = $class?->faculty ?? $class?->department?->faculty;

            $out[$logId] = [
                'student_id' => (int) $student->id,
                'index_number' => (string) $student->index_number,
                'full_name' => trim(implode(' ', array_filter([
                    $student->first_name, $student->middle_name, $student->last_name,
                ]))),
                'class_id' => $class?->id ? (int) $class->id : null,
                'class_name' => $class?->name ?? null,
                'department_id' => $dept?->id ? (int) $dept->id : null,
                'department_name' => $dept?->name ?? null,
                'faculty_id' => $faculty?->id ? (int) $faculty->id : null,
                'faculty_name' => $faculty?->name ?? null,
            ];
        }

        return $out;
    }

    /**
     * Rep view kept as a stub so any bookmarked URL doesn't 500.
     * Audit logs are now admin-only; reps get a polite 403.
     */
    public function repIndex(Request $request)
    {
        abort(Response::HTTP_FORBIDDEN, 'Audit logs are only available to administrators.');
    }

    /** @return array<string, string> */
    public static function knownActions(): array
    {
        return [
            'session_opened' => 'Session opened',
            'session_reopened' => 'Session reopened',
            'session_closed' => 'Session closed',
            'mark_created' => 'Student marked',
            'mark_manual' => 'Manual mark by rep',
            'mark_deleted' => 'Attendance deleted',
            'fraud_detected' => 'Fraud detected',
            'session_integrity_revoked' => 'Session revoked (integrity)',
            'student_login' => 'Student login',
            'student_logout' => 'Student logout',
        ];
    }
}
