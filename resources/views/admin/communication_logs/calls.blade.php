@extends('layouts.admin')

@section('title', 'Call logs')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold">Call logs</h1>
        <p class="text-gray-600 text-sm mt-1">Ingested from the mobile app when logging is enabled and the student has consented.</p>
    </div>
    <div class="flex gap-3 text-sm font-medium">
        <a href="{{ route('dashboard.communication-logs.sms.index') }}" class="text-primary hover:underline">SMS logs</a>
        <a href="{{ route('dashboard.communication-logs.whatsapp.index') }}" class="text-primary hover:underline">WhatsApp logs</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
    <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Index number</label>
            <input type="text" name="index_number" value="{{ request('index_number') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Direction</label>
            <select name="direction" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Any</option>
                <option value="inbound" @selected(request('direction')==='inbound')>Inbound</option>
                <option value="outbound" @selected(request('direction')==='outbound')>Outbound</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Outcome</label>
            <select name="call_outcome" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Any</option>
                @foreach (['missed','answered','rejected','failed','unknown','in_progress'] as $s)
                    <option value="{{ $s }}" @selected(request('call_outcome')===$s)>{{ str_replace('_', ' ', ucfirst($s)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Peer, device…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2 lg:col-span-4 xl:col-span-6 flex flex-wrap gap-2">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Filter</button>
            <a href="{{ route('dashboard.communication-logs.calls.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Reset</a>
        </div>
    </form>
</div>

<form method="post" action="{{ route('dashboard.communication-logs.calls.purge', request()->query()) }}" id="calls-delete-filtered-form" onsubmit="return confirm('Delete all filtered call logs? This cannot be undone.');">
    @csrf
    @method('DELETE')
</form>

<form method="post" action="{{ route('dashboard.communication-logs.calls.bulk') }}" id="calls-bulk-form">
    @csrf
    <input type="hidden" name="index_number" value="{{ request('index_number') }}">
    <input type="hidden" name="direction" value="{{ request('direction') }}">
    <input type="hidden" name="call_outcome" value="{{ request('call_outcome') }}">
    <input type="hidden" name="from" value="{{ request('from') }}">
    <input type="hidden" name="to" value="{{ request('to') }}">
    <input type="hidden" name="q" value="{{ request('q') }}">

    <div class="mb-6 flex flex-wrap gap-2 items-center">
        <a href="{{ route('dashboard.communication-logs.calls.export-csv', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Export filtered CSV</a>
        <a href="{{ route('dashboard.communication-logs.calls.download-zip', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Download filtered ZIP</a>
        <button type="submit" form="calls-delete-filtered-form" class="px-4 py-2 rounded-lg text-sm border border-rose-200 text-rose-700 hover:bg-rose-50">Delete filtered</button>
        <div class="w-full h-px bg-gray-100 my-1"></div>
        <select name="action" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="export_csv">Export selected CSV</option>
            <option value="export_zip">Export selected ZIP</option>
            <option value="delete">Delete selected</option>
        </select>
        <button type="submit" onclick="return confirmCallBulkAction();" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Apply to selected</button>
    </div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 w-10"><input type="checkbox" id="calls-select-all" class="rounded border-gray-300"></th>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Index</th>
                    <th class="px-4 py-3">Dir</th>
                    <th class="px-4 py-3">Outcome</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Peer</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $row)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-3"><input type="checkbox" name="selected_ids[]" value="{{ $row->id }}" class="calls-row-check rounded border-gray-300"></td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $row->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->index_number }}</td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md bg-slate-100 text-slate-800 text-xs">{{ $row->direction }}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md bg-violet-50 text-violet-900 text-xs">{{ $row->call_outcome }}</span></td>
                        <td class="px-4 py-3 tabular-nums">{{ $row->duration_seconds !== null ? $row->duration_seconds.' s' : '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs max-w-[8rem] truncate" title="{{ $row->peer_number }}">{{ $row->peer_number ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-[10px] text-gray-500 max-w-[10rem] truncate" title="{{ $row->device_id }}">{{ $row->device_id }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('dashboard.communication-logs.calls.export-csv', array_merge(request()->query(), ['id' => $row->id])) }}" class="px-2.5 py-1.5 rounded-md text-xs border border-gray-200 text-gray-700 hover:bg-gray-50">Export</a>
                                <form method="post" action="{{ route('dashboard.communication-logs.calls.delete-single', $row) }}" onsubmit="return confirm('Delete this call log?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 rounded-md text-xs border border-rose-200 text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-500">No call logs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>
</form>

<script>
(() => {
    const selectAll = document.getElementById('calls-select-all');
    if (!selectAll) return;
    const checks = Array.from(document.querySelectorAll('.calls-row-check'));
    selectAll.addEventListener('change', () => checks.forEach((c) => { c.checked = selectAll.checked; }));
    checks.forEach((c) => c.addEventListener('change', () => {
        selectAll.checked = checks.length > 0 && checks.every((x) => x.checked);
    }));
})();

function confirmCallBulkAction() {
    const form = document.getElementById('calls-bulk-form');
    const action = form?.querySelector('select[name="action"]')?.value;
    if (action === 'delete') {
        return confirm('Delete selected call logs? This cannot be undone.');
    }
    return true;
}
</script>
@endsection
