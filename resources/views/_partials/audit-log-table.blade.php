@props(['logs', 'available' => true, 'actions' => []])

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
                        $actionLabel = $actions[$action] ?? str_replace('_', ' ', $action);
                        $palette = match (true) {
                            in_array($action, ['mark_deleted', 'fraud_detected', 'session_integrity_revoked'], true) => 'bg-red-50 text-red-700 border-red-100',
                            in_array($action, ['mark_manual'], true) => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                            in_array($action, ['session_opened', 'session_reopened'], true) => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            in_array($action, ['session_closed'], true) => 'bg-slate-100 text-slate-700 border-slate-200',
                            default => 'bg-gray-50 text-gray-700 border-gray-200',
                        };
                        $payload = $log->payload ?? [];

                        // Build a self-contained, JSON-encoded snapshot
                        // for the detail modal. Keep it readable + safe.
                        $rowDetail = [
                            'id' => (int) $log->id,
                            'when' => optional($log->created_at)->toDateTimeString(),
                            'when_human' => optional($log->created_at)->diffForHumans(),
                            'action' => $action,
                            'action_label' => $actionLabel,
                            'palette' => $palette,
                            'actor' => [
                                'name' => $log->actor_name,
                                'role' => $log->actor_role,
                                'id'   => $log->actor_id,
                            ],
                            'target' => [
                                'subject_type' => $log->subject_type,
                                'subject_id' => $log->subject_id,
                                'course_id' => $log->course_id,
                                'class_id' => $log->class_id,
                            ],
                            'network' => [
                                'ip' => $log->ip,
                                'user_agent' => $log->user_agent,
                                'device_fingerprint' => $log->device_fingerprint,
                            ],
                            'payload' => $payload,
                        ];
                    @endphp
                    <tr class="align-top hover:bg-gray-50/70 cursor-pointer transition-colors"
                        data-audit-row
                        data-audit-detail='@json($rowDetail)'
                        tabindex="0"
                        title="Click to see full event details"
                        role="button">
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
                                {{ $actionLabel }}
                            </span>
                            @if(! empty($payload['reason']))
                                <div class="mt-1 text-[10.5px] text-gray-600 italic line-clamp-1">“{{ $payload['reason'] }}”</div>
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

{{-- Detail modal — single instance reused for every row click. --}}
<div id="audit-modal" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
     role="dialog" aria-modal="true" aria-labelledby="audit-modal-title">
    <div class="relative w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl border border-gray-200 max-h-[92vh] sm:max-h-[90vh] flex flex-col"
         data-audit-modal-card>
        <div class="flex items-start justify-between gap-4 p-4 sm:p-5 border-b border-gray-100">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <span id="audit-modal-action"
                          class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold bg-gray-50 text-gray-700 border-gray-200"></span>
                    <span id="audit-modal-id" class="text-[10px] font-mono text-gray-400"></span>
                </div>
                <h3 id="audit-modal-title" class="text-base font-bold text-gray-900 leading-tight">Event details</h3>
                <div class="mt-0.5 flex items-center gap-2 text-[11px] text-gray-500">
                    <span id="audit-modal-when" class="tabular-nums"></span>
                    <span id="audit-modal-relative" class="text-gray-400"></span>
                </div>
            </div>
            <button type="button" data-audit-close
                    class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"
                    aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="overflow-y-auto p-4 sm:p-5 space-y-5 text-sm">
            <section>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Actor</h4>
                <dl class="grid grid-cols-[110px_1fr] sm:grid-cols-3 gap-x-3 gap-y-1.5 text-[12px]">
                    <dt class="text-gray-500">Name</dt>
                    <dd id="audit-modal-actor-name" class="sm:col-span-2 font-medium text-gray-900">—</dd>
                    <dt class="text-gray-500">Role</dt>
                    <dd id="audit-modal-actor-role" class="sm:col-span-2 capitalize text-gray-800">—</dd>
                    <dt class="text-gray-500">Internal id</dt>
                    <dd id="audit-modal-actor-id" class="sm:col-span-2 font-mono text-gray-700">—</dd>
                </dl>
            </section>

            <section>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Target</h4>
                <dl class="grid grid-cols-[110px_1fr] sm:grid-cols-3 gap-x-3 gap-y-1.5 text-[12px]">
                    <dt class="text-gray-500">Subject</dt>
                    <dd id="audit-modal-subject" class="sm:col-span-2 text-gray-800">—</dd>
                    <dt class="text-gray-500">Course</dt>
                    <dd id="audit-modal-course" class="sm:col-span-2 text-gray-800">—</dd>
                    <dt class="text-gray-500">Class</dt>
                    <dd id="audit-modal-class" class="sm:col-span-2 text-gray-800">—</dd>
                </dl>
            </section>

            <section>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Network &amp; device</h4>
                <dl class="grid grid-cols-[110px_1fr] sm:grid-cols-3 gap-x-3 gap-y-1.5 text-[12px]">
                    <dt class="text-gray-500">IP address</dt>
                    <dd id="audit-modal-ip" class="sm:col-span-2 font-mono text-gray-800">—</dd>
                    <dt class="text-gray-500">User agent</dt>
                    <dd id="audit-modal-ua" class="sm:col-span-2 text-gray-700 break-words leading-snug">—</dd>
                    <dt class="text-gray-500">Device fingerprint</dt>
                    <dd id="audit-modal-fp" class="sm:col-span-2 font-mono text-gray-700 break-all">—</dd>
                </dl>
            </section>

            <section>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Raw payload</h4>
                    <button type="button" data-audit-copy class="text-[11px] font-medium text-primary hover:underline">
                        <i class="fas fa-copy text-[10px]"></i> Copy JSON
                    </button>
                </div>
                <pre id="audit-modal-payload"
                     class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-[11.5px] font-mono text-gray-800 whitespace-pre-wrap break-words leading-relaxed max-h-[280px] overflow-y-auto">{}</pre>
            </section>
        </div>

        <div class="flex justify-end gap-2 p-3 sm:p-4 border-t border-gray-100 bg-gray-50/60 rounded-b-none sm:rounded-b-2xl pb-[env(safe-area-inset-bottom,0)]">
            <button type="button" data-audit-close
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Close
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('audit-modal');
        if (!modal) return;
        const card = modal.querySelector('[data-audit-modal-card]');

        const els = {
            action:  document.getElementById('audit-modal-action'),
            id:      document.getElementById('audit-modal-id'),
            when:    document.getElementById('audit-modal-when'),
            rel:     document.getElementById('audit-modal-relative'),
            actor:   document.getElementById('audit-modal-actor-name'),
            role:    document.getElementById('audit-modal-actor-role'),
            actorId: document.getElementById('audit-modal-actor-id'),
            subject: document.getElementById('audit-modal-subject'),
            course:  document.getElementById('audit-modal-course'),
            class_:  document.getElementById('audit-modal-class'),
            ip:      document.getElementById('audit-modal-ip'),
            ua:      document.getElementById('audit-modal-ua'),
            fp:      document.getElementById('audit-modal-fp'),
            payload: document.getElementById('audit-modal-payload'),
        };

        const setText = (el, val) => { if (el) el.textContent = (val === null || val === undefined || val === '') ? '—' : String(val); };

        function open(detail) {
            const palette = (detail.palette || 'bg-gray-50 text-gray-700 border-gray-200').split(' ');
            els.action.className = 'inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold ' + palette.join(' ');
            setText(els.action, detail.action_label || detail.action);
            setText(els.id, detail.id ? '#' + detail.id : '');
            setText(els.when, detail.when || '');
            setText(els.rel, detail.when_human ? '· ' + detail.when_human : '');

            const actor = detail.actor || {};
            setText(els.actor, actor.name);
            setText(els.role, actor.role);
            setText(els.actorId, actor.id);

            const t = detail.target || {};
            const subj = t.subject_type ? `${t.subject_type} #${t.subject_id ?? ''}` : '—';
            setText(els.subject, subj);
            setText(els.course,  t.course_id ? '#' + t.course_id : '—');
            setText(els.class_,  t.class_id  ? '#' + t.class_id  : '—');

            const n = detail.network || {};
            setText(els.ip, n.ip);
            setText(els.ua, n.user_agent);
            setText(els.fp, n.device_fingerprint);

            const p = detail.payload || {};
            try {
                els.payload.textContent = Object.keys(p).length === 0 ? '{}' : JSON.stringify(p, null, 2);
            } catch (e) {
                els.payload.textContent = String(p);
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            modal.querySelector('[data-audit-close]')?.focus();
        }

        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-audit-row]').forEach(function (row) {
            const detailJson = row.getAttribute('data-audit-detail');
            if (!detailJson) return;
            let detail;
            try { detail = JSON.parse(detailJson); } catch (e) { return; }

            const handler = (ev) => {
                if (ev.target.closest('a, button, form')) return;
                open(detail);
            };
            row.addEventListener('click', handler);
            row.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    open(detail);
                }
            });
        });

        modal.addEventListener('click', (ev) => {
            if (ev.target === modal) close();
        });
        document.querySelectorAll('[data-audit-close]').forEach(b => b.addEventListener('click', close));
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });

        const copyBtn = document.querySelector('[data-audit-copy]');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const text = els.payload.textContent || '';
                navigator.clipboard?.writeText(text).then(() => {
                    const original = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="fas fa-check text-[10px]"></i> Copied';
                    setTimeout(() => { copyBtn.innerHTML = original; }, 1200);
                });
            });
        }
    })();
</script>
@endpush
@endif
