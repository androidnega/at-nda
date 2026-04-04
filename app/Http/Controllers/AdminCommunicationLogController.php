<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\LoggedSms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only review of communication logs (PII — keep behind admin.only).
 */
class AdminCommunicationLogController extends Controller
{
    public function sms(Request $request): View
    {
        $query = LoggedSms::query()
            ->with(['student:id,index_number,first_name,last_name']);

        $this->applySmsFilters($query, $request);

        $sort = (string) $request->query('sort', 'occurred_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['occurred_at', 'direction', 'delivery_status', 'index_number', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'occurred_at';
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        $logs = $query->paginate(40)->withQueryString();

        return view('admin.communication_logs.sms', compact('logs'));
    }

    public function calls(Request $request): View
    {
        $query = CallLog::query()
            ->with(['student:id,index_number,first_name,last_name']);

        $this->applyCallFilters($query, $request);

        $sort = (string) $request->query('sort', 'occurred_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['occurred_at', 'direction', 'call_outcome', 'index_number', 'duration_seconds', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'occurred_at';
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        $logs = $query->paginate(40)->withQueryString();

        return view('admin.communication_logs.calls', compact('logs'));
    }

    private function applySmsFilters(Builder $query, Request $request): void
    {
        if ($request->filled('index_number')) {
            $query->where('index_number', 'like', '%'.strtoupper(trim((string) $request->index_number)).'%');
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', 'like', '%'.trim((string) $request->device_id).'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('peer_number', 'like', $needle)
                    ->orWhere('body_preview', 'like', $needle)
                    ->orWhere('device_id', 'like', $needle)
                    ->orWhere('client_record_id', 'like', $needle);
            });
        }
    }

    private function applyCallFilters(Builder $query, Request $request): void
    {
        if ($request->filled('index_number')) {
            $query->where('index_number', 'like', '%'.strtoupper(trim((string) $request->index_number)).'%');
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('call_outcome')) {
            $query->where('call_outcome', $request->call_outcome);
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', 'like', '%'.trim((string) $request->device_id).'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('peer_number', 'like', $needle)
                    ->orWhere('device_id', 'like', $needle)
                    ->orWhere('client_record_id', 'like', $needle);
            });
        }
    }
}
