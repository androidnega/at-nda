@props(['logs', 'available' => true])

@if(! $available)
    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-6 text-center text-sm text-amber-900">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Audit logging isn't set up on this server yet. Run <code class="font-mono bg-white/60 px-1 py-0.5 rounded">php artisan migrate</code> to enable it.
    </div>
@elseif($logs->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
        No audit events yet.
    </div>
@else
<div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="text-left">
                    <th class="px-3 py-2 font-semibold text-gray-600">When</th>
                    <th class="px-3 py-2 font-semibold text-gray-600">Actor</th>
                    <th class="px-3 py-2 font-semibold text-gray-600">Action</th>
                    <th class="px-3 py-2 font-semibold text-gray-600">Target</th>
                    <th class="px-3 py-2 font-semibold text-gray-600">IP / device</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($logs as $log)
                    @php
                        $action = (string) $log->action;
                        $palette = match (true) {
                            in_array($action, ['mark_deleted', 'fraud_detected', 'session_integrity_revoked'], true) => 'bg-red-50 text-red-700 border-red-100',
                            in_array($action, ['mark_manual'], true) => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                            in_array($action, ['session_opened', 'session_reopened'], true) => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            in_array($action, ['session_closed'], true) => 'bg-slate-100 text-slate-700 border-slate-200',
                            default => 'bg-gray-50 text-gray-700 border-gray-200',
                        };
                        $payload = $log->payload ?? [];
                    @endphp
                    <tr class="align-top">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                            <div class="tabular-nums">{{ $log->created_at?->format('M j, Y') }}</div>
                            <div class="text-[10.5px] text-gray-500 tabular-nums">{{ $log->created_at?->format('g:i:s A') }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="font-medium text-gray-900 leading-tight">{{ $log->actor_name ?: '—' }}</div>
                            <div class="text-[10.5px] text-gray-500 capitalize">{{ $log->actor_role ?? '—' }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-semibold {{ $palette }}">
                                {{ $actions[$action] ?? str_replace('_', ' ', $action) }}
                            </span>
                            @if(! empty($payload['reason']))
                                <div class="mt-1 text-[10.5px] text-gray-600 italic">“{{ $payload['reason'] }}”</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-[11px] text-gray-700">
                            @if($log->course_id)
                                <span class="inline-flex items-center gap-1 text-gray-700">
                                    <i class="fas fa-book text-gray-400 text-[10px]"></i>
                                    Course #{{ $log->course_id }}
                                </span>
                            @endif
                            @if($log->class_id)
                                <span class="ml-1 inline-flex items-center gap-1 text-gray-700">
                                    <i class="fas fa-users text-gray-400 text-[10px]"></i>
                                    Class #{{ $log->class_id }}
                                </span>
                            @endif
                            @if($log->subject_type)
                                <div class="text-[10.5px] text-gray-500 mt-0.5">{{ $log->subject_type }} #{{ $log->subject_id }}</div>
                            @endif
                            @if(! empty($payload['index_number']))
                                <div class="text-[10.5px] text-gray-700 font-mono mt-0.5">{{ $payload['index_number'] }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-[10.5px] text-gray-600">
                            @if($log->ip)<div class="font-mono">{{ $log->ip }}</div>@endif
                            @if($log->user_agent)
                                <div class="truncate max-w-[200px]" title="{{ $log->user_agent }}">{{ $log->user_agent }}</div>
                            @endif
                            @if($log->device_fingerprint)
                                <div class="font-mono text-gray-400">{{ substr($log->device_fingerprint, 0, 12) }}…</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div class="px-3 py-2 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>
@endif
