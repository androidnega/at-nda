@extends('layouts.admin')

@section('title', 'WhatsApp logs')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold">WhatsApp logs</h1>
        <p class="text-gray-600 text-sm mt-1">Copied WhatsApp messages uploaded from the mobile app after consent.</p>
    </div>
    <div class="flex gap-3 text-sm font-medium">
        <a href="{{ route('dashboard.communication-logs.sms.index') }}" class="text-primary hover:underline">SMS logs</a>
        <a href="{{ route('dashboard.communication-logs.calls.index') }}" class="text-primary hover:underline">Call logs</a>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
    <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Index number</label>
            <input type="text" name="index_number" value="{{ request('index_number') }}" placeholder="BC/…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Source app</label>
            <select name="source_app" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Any</option>
                <option value="whatsapp" @selected(request('source_app')==='whatsapp')>WhatsApp</option>
                <option value="whatsapp_business" @selected(request('source_app')==='whatsapp_business')>WhatsApp Business</option>
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
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Sender, body, device…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2 lg:col-span-4 xl:col-span-6 flex flex-wrap gap-2">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Filter</button>
            <a href="{{ route('dashboard.communication-logs.whatsapp.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Reset</a>
        </div>
    </form>
</div>

<form method="post" action="{{ route('dashboard.communication-logs.whatsapp.purge', request()->query()) }}" id="wa-delete-filtered-form" onsubmit="return confirm('Delete all filtered WhatsApp logs? This cannot be undone.');">
    @csrf
    @method('DELETE')
</form>

<form method="post" action="{{ route('dashboard.communication-logs.whatsapp.bulk') }}" id="wa-bulk-form">
    @csrf
    <input type="hidden" name="index_number" value="{{ request('index_number') }}">
    <input type="hidden" name="source_app" value="{{ request('source_app') }}">
    <input type="hidden" name="from" value="{{ request('from') }}">
    <input type="hidden" name="to" value="{{ request('to') }}">
    <input type="hidden" name="q" value="{{ request('q') }}">

    <div class="mb-6 flex flex-wrap gap-2 items-center">
        <a href="{{ route('dashboard.communication-logs.whatsapp.export-csv', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Export filtered CSV</a>
        <a href="{{ route('dashboard.communication-logs.whatsapp.download-zip', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Download filtered ZIP</a>
        <button type="submit" form="wa-delete-filtered-form" class="px-4 py-2 rounded-lg text-sm border border-rose-200 text-rose-700 hover:bg-rose-50">Delete filtered</button>
        <div class="w-full h-px bg-gray-100 my-1"></div>
        <select name="action" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="export_csv">Export selected CSV</option>
            <option value="export_zip">Export selected ZIP</option>
            <option value="delete">Delete selected</option>
        </select>
        <button type="submit" onclick="return confirmBulkAction();" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Apply to selected</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox" id="wa-select-all" class="rounded border-gray-300">
                    </th>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Index</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Sender hint</th>
                    <th class="px-4 py-3">Message</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $row)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="selected_ids[]" value="{{ $row->id }}" class="wa-row-check rounded border-gray-300">
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $row->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->index_number }}</td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-900 text-xs">{{ $row->source_app }}</span></td>
                        <td class="px-4 py-3 text-gray-700 max-w-[10rem] truncate" title="{{ $row->sender_hint }}">{{ $row->sender_hint ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-md truncate" title="{{ $row->body_preview }}">{{ \Illuminate\Support\Str::limit($row->body_preview, 100) }}</td>
                        <td class="px-4 py-3 font-mono text-[10px] text-gray-500 max-w-[10rem] truncate" title="{{ $row->device_id }}">{{ $row->device_id }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('dashboard.communication-logs.whatsapp.export-csv', array_merge(request()->query(), ['id' => $row->id])) }}" class="px-2.5 py-1.5 rounded-md text-xs border border-gray-200 text-gray-700 hover:bg-gray-50">Export</a>
                                <form method="post" action="{{ route('dashboard.communication-logs.whatsapp.delete-single', $row) }}" onsubmit="return confirm('Delete this message?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 rounded-md text-xs border border-rose-200 text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-500">No WhatsApp logs yet.</td>
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
  const all = document.getElementById('wa-select-all');
  const checks = Array.from(document.querySelectorAll('.wa-row-check'));
  if (all) {
    all.addEventListener('change', () => {
      checks.forEach((c) => c.checked = all.checked);
    });
  }
})();

function confirmBulkAction() {
  const selected = document.querySelectorAll('.wa-row-check:checked').length;
  if (selected < 1) {
    alert('Select at least one message.');
    return false;
  }
  const action = document.querySelector('#wa-bulk-form select[name="action"]')?.value ?? '';
  if (action === 'delete') {
    return confirm(`Delete ${selected} selected message(s)? This cannot be undone.`);
  }
  return true;
}
</script>
@endsection
