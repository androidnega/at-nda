@extends('layouts.admin')

@section('title', 'SMS logs')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold">SMS logs</h1>
        <p class="text-gray-600 text-sm mt-1">Ingested from the mobile app when logging is enabled and the student has consented.</p>
    </div>
    <a href="{{ route('dashboard.communication-logs.calls.index') }}" class="text-sm font-medium text-primary hover:underline">View call logs →</a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
    <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Index number</label>
            <input type="text" name="index_number" value="{{ request('index_number') }}" placeholder="BC/…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
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
            <label class="block text-xs font-medium text-gray-500 mb-1">Delivery</label>
            <select name="delivery_status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Any</option>
                @foreach (['pending','sent','delivered','failed','unknown'] as $s)
                    <option value="{{ $s }}" @selected(request('delivery_status')===$s)>{{ ucfirst($s) }}</option>
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
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Peer, body, device…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2 lg:col-span-4 xl:col-span-6 flex flex-wrap gap-2">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Filter</button>
            <a href="{{ route('dashboard.communication-logs.sms.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-200 text-gray-700 hover:bg-gray-50">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3"><a href="{{ request()->fullUrlWithQuery(['sort'=>'occurred_at','dir'=>request('sort')==='occurred_at' && request('dir')==='asc'?'desc':'asc']) }}" class="hover:text-primary">When</a></th>
                    <th class="px-4 py-3">Index</th>
                    <th class="px-4 py-3">Dir</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Peer</th>
                    <th class="px-4 py-3">Preview</th>
                    <th class="px-4 py-3">Device</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $row)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $row->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->index_number }}</td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md bg-slate-100 text-slate-800 text-xs">{{ $row->direction }}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md bg-amber-50 text-amber-900 text-xs">{{ $row->delivery_status }}</span></td>
                        <td class="px-4 py-3 font-mono text-xs max-w-[8rem] truncate" title="{{ $row->peer_number }}">{{ $row->peer_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-md truncate" title="{{ $row->body_preview }}">{{ $row->body_preview ? \Illuminate\Support\Str::limit($row->body_preview, 80) : '—' }}</td>
                        <td class="px-4 py-3 font-mono text-[10px] text-gray-500 max-w-[10rem] truncate" title="{{ $row->device_id }}">{{ $row->device_id }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-500">No SMS logs yet.</td>
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
