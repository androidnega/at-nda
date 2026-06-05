<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Student;
use App\Support\RepCourseAccess;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
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

        return view('admin.audit-logs', [
            'logs' => $logs,
            'available' => $available,
            'actions' => self::knownActions(),
        ]);
    }

    /**
     * Rep view: only logs that touch courses / sessions the rep manages.
     */
    public function repIndex(Request $request): View
    {
        $rep = Student::find($request->session()->get('student_id'));
        $available = SchemaFeatures::hasAuditLogs() && $rep instanceof Student;

        $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 40);
        if ($available) {
            $courseIds = RepCourseAccess::coursesQueryForRep($rep)->pluck('courses.id')->all();
            $classIds = \App\Models\ClassRep::query()
                ->where('student_id', $rep->id)
                ->pluck('class_id')
                ->unique()
                ->values()
                ->all();

            $query = AuditLog::query()
                ->where(function ($q) use ($courseIds, $classIds) {
                    if (! empty($courseIds)) {
                        $q->orWhereIn('course_id', $courseIds);
                    }
                    if (! empty($classIds)) {
                        $q->orWhereIn('class_id', $classIds);
                    }
                })
                ->orderByDesc('id');

            if ($action = $request->query('action')) {
                $query->where('action', $action);
            }

            $logs = $query->paginate(40)->withQueryString();
        }

        return view('classrep.audit-logs', [
            'logs' => $logs,
            'available' => $available,
            'actions' => self::knownActions(),
        ]);
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
