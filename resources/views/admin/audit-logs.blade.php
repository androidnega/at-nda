@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('content')
<div class="mb-5 sm:mb-6">
    <h1 class="text-xl sm:text-2xl font-bold flex items-center gap-2">
        <i class="fas fa-shield-halved text-primary/80"></i>
        Audit Logs
    </h1>
    <p class="text-gray-600 text-xs sm:text-sm mt-1">Every attendance session, mark, deletion, login, and integrity event in one place. Tap any row for full details.</p>
</div>

<form method="get" action="{{ route('dashboard.audit-logs.index') }}" class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 mb-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto] gap-2.5 sm:gap-2 sm:items-end">
        <div class="min-w-0">
            <label for="search" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Search actor / IP</label>
            <input id="search" name="search" value="{{ request('search') }}"
                placeholder="e.g. Kwofie · 105.21.0.4 · login"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
        </div>
        <div>
            <label for="action" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Action</label>
            <select id="action" name="action" class="w-full sm:w-auto border border-gray-200 rounded-lg px-2 py-2 text-sm bg-white">
                <option value="">All</option>
                @foreach($actions as $key => $label)
                    <option value="{{ $key }}" {{ request('action') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="role" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Role</label>
            <select id="role" name="role" class="w-full sm:w-auto border border-gray-200 rounded-lg px-2 py-2 text-sm bg-white">
                <option value="">All</option>
                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                <option value="rep" {{ request('role') === 'rep' ? 'selected' : '' }}>Class Rep</option>
                <option value="lecturer" {{ request('role') === 'lecturer' ? 'selected' : '' }}>Lecturer</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
            </select>
        </div>
        <div class="flex gap-2 items-center">
            <button type="submit" class="flex-1 sm:flex-none bg-primary text-white text-sm font-semibold px-3 py-2 rounded-lg hover:bg-primary/90 inline-flex items-center justify-center gap-1.5">
                <i class="fas fa-filter text-[10px]"></i> Filter
            </button>
            <a href="{{ route('dashboard.audit-logs.index') }}" class="text-xs text-gray-500 hover:text-gray-800 px-2 py-2">Reset</a>
        </div>
    </div>
</form>

@include('_partials.audit-log-table', ['logs' => $logs, 'available' => $available, 'actions' => $actions, 'studentMetaByLog' => $studentMetaByLog ?? []])
@endsection
