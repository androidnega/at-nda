<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
