@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold flex items-center gap-2">
        <i class="fas fa-shield-halved text-primary/80"></i>
        Audit Logs
    </h1>
    <p class="text-gray-600 text-sm mt-1">Every attendance session, mark, deletion, login, and integrity event in one place.</p>
</div>

<form method="get" action="{{ route('dashboard.audit-logs.index') }}" class="bg-white rounded-xl border border-gray-200 p-3 mb-4">
    <div class="flex flex-wrap items-end gap-2">
        <div class="flex-1 min-w-[200px]">
            <label for="search" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Search actor / IP</label>
            <input id="search" name="search" value="{{ request('search') }}"
                placeholder="e.g. Kwofie · 105.21.0.4 · login"
                class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
        </div>
        <div>
            <label for="action" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Action</label>
            <select id="action" name="action" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm bg-white">
                <option value="">All</option>
                @foreach($actions as $key => $label)
                    <option value="{{ $key }}" {{ request('action') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="role" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Role</label>
            <select id="role" name="role" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm bg-white">
                <option value="">All</option>
                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                <option value="rep" {{ request('role') === 'rep' ? 'selected' : '' }}>Class Rep</option>
                <option value="lecturer" {{ request('role') === 'lecturer' ? 'selected' : '' }}>Lecturer</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
            </select>
        </div>
        <button type="submit" class="bg-primary text-white text-sm font-semibold px-3 py-1.5 rounded-lg hover:bg-primary/90">
            <i class="fas fa-filter text-[10px]"></i> Filter
        </button>
        <a href="{{ route('dashboard.audit-logs.index') }}" class="text-xs text-gray-500 hover:text-gray-800">Reset</a>
    </div>
</form>

@include('_partials.audit-log-table', ['logs' => $logs, 'available' => $available, 'actions' => $actions])
@endsection
