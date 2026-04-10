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

<div class="mb-6 flex flex-wrap gap-2">
    <a href="{{ route('dashboard.communication-logs.whatsapp.export-csv', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Export CSV</a>
    <a href="{{ route('dashboard.communication-logs.whatsapp.download-zip', request()->query()) }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Download ZIP</a>
    <form method="post" action="{{ route('dashboard.communication-logs.whatsapp.purge', request()->query()) }}" onsubmit="return confirm('Delete all filtered WhatsApp logs? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="px-4 py-2 rounded-lg text-sm border border-rose-200 text-rose-700 hover:bg-rose-50">Delete filtered</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Index</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Sender hint</th>
                    <th class="px-4 py-3">Message</th>
                    <th class="px-4 py-3">Device</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $row)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $row->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->index_number }}</td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-900 text-xs">{{ $row->source_app }}</span></td>
                        <td class="px-4 py-3 text-gray-700 max-w-[10rem] truncate" title="{{ $row->sender_hint }}">{{ $row->sender_hint ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-md truncate" title="{{ $row->body_preview }}">{{ \Illuminate\Support\Str::limit($row->body_preview, 100) }}</td>
                        <td class="px-4 py-3 font-mono text-[10px] text-gray-500 max-w-[10rem] truncate" title="{{ $row->device_id }}">{{ $row->device_id }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">No WhatsApp logs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
