@extends('layouts.classrep')

@section('title', 'Audit Logs')

@section('content')
<div class="mb-4">
    <a href="{{ route('dashboard.class-attendance.index') }}" class="text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1 mb-2">
        <i class="fas fa-arrow-left text-[10px]"></i> Back to attendance hub
    </a>
    <h1 class="text-lg sm:text-xl font-bold text-gray-900">Audit log</h1>
    <p class="text-xs text-gray-500 mt-1">Every action on the courses and classes you manage — sessions, marks, deletions, logins.</p>
</div>

<form method="get" action="{{ route('dashboard.class-attendance.audit-logs') }}" class="bg-white rounded-xl border border-gray-200 p-3 mb-4">
    <div class="flex flex-wrap items-end gap-2">
        <div>
            <label for="action" class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Action</label>
            <select id="action" name="action" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm bg-white">
                <option value="">All actions</option>
                @foreach($actions as $key => $label)
                    <option value="{{ $key }}" {{ request('action') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-primary text-white text-sm font-semibold px-3 py-1.5 rounded-lg hover:bg-primary/90">
            <i class="fas fa-filter text-[10px]"></i> Filter
        </button>
        <a href="{{ route('dashboard.class-attendance.audit-logs') }}" class="text-xs text-gray-500 hover:text-gray-800">Reset</a>
    </div>
</form>

@include('_partials.audit-log-table', ['logs' => $logs, 'available' => $available, 'actions' => $actions])
@endsection
