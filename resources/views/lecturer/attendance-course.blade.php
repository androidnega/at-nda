@extends('layouts.admin')

@section('title', $course->course_name . ' — Attendance')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.teaching.attendance.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> All courses
    </a>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-primary">{{ $course->course_name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $course->assignedClassesLabel() ?: '—' }} · Attendance records</p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('dashboard.teaching.attendance.course.pdf', $course) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-red-200 bg-red-50 text-sm font-medium text-red-800 hover:bg-red-100">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="{{ route('dashboard.teaching.attendance.course.export-json', $course) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-file-code"></i> JSON
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 p-3 bg-green-50 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 p-3 bg-red-50 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
@endif

@if(isset($attendanceWeeks) && $attendanceWeeks->isNotEmpty())
<div class="mb-6 bg-white rounded-xl border border-gray-100 p-4">
    <p class="text-sm font-semibold text-gray-800">Teaching weeks</p>
    <ul class="mt-3 divide-y divide-gray-100">
        @foreach($attendanceWeeks as $week)
        <li class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <span class="font-medium text-gray-900">Week {{ $week->week_number }}</span>
                @if($week->week_date)<span class="text-sm text-gray-500 ml-2">{{ $week->week_date->format('M j, Y') }}</span>@endif
                @if($week->isCancelled())<span class="ml-2 text-amber-800 text-sm font-semibold">Cancelled</span>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if($week->isCancelled())
                <form action="{{ route('lecturer.courses.week.uncancel', [$course, $week]) }}" method="post">@csrf
                    <button type="submit" class="text-sm text-primary font-medium hover:underline">Restore week</button>
                </form>
                @else
                <form action="{{ route('lecturer.courses.week.cancel', [$course, $week]) }}" method="post" class="flex flex-wrap items-center gap-2">@csrf
                    <input type="text" name="note" placeholder="Note (optional)" class="text-sm border border-gray-200 rounded-lg px-2 py-1">
                    <button type="submit" class="text-sm rounded-lg bg-amber-700 text-white px-3 py-1.5 font-medium">Cancel week</button>
                </form>
                @endif
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

<div class="mb-6 bg-white rounded-xl border border-gray-100 p-4">
    <p class="text-sm font-semibold text-gray-800">Restore attendance (JSON)</p>
    <form action="{{ route('dashboard.teaching.attendance.course.import-json', $course) }}" method="post" enctype="multipart/form-data" class="mt-3 flex flex-wrap gap-2">@csrf
        <input type="file" name="backup" accept=".json" required class="text-sm">
        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Upload</button>
    </form>
</div>

<form method="GET" action="{{ route('dashboard.teaching.attendance.course', $course) }}" id="attendance-filters-form" class="mb-4 flex flex-wrap gap-2 items-end">
    <div>
        <label class="block text-xs text-gray-500 mb-1">From</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">To</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs text-gray-500 mb-1">Index number</label>
        <input type="search" name="search" id="attendance-search" value="{{ request('search') }}" placeholder="Search…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Apply</button>
</form>

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Index</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Time</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($attendances as $a)
            <tr>
                <td class="px-4 py-3 font-mono">{{ $a->student?->index_number ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $a->attendance_time->format('M d, Y H:i') }}</td>
                <td class="px-4 py-3"><span class="text-green-800 bg-green-50 px-2 py-0.5 rounded text-xs">{{ $a->status }}</span></td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-4 py-10 text-center text-gray-500">No attendance marks in this range</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($attendances->hasPages())
    <div class="p-4 border-t border-gray-100">{{ $attendances->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('attendance-filters-form');
    const search = document.getElementById('attendance-search');
    if (!form || !search) return;
    let t;
    search.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () { form.requestSubmit(); }, 350);
    });
})();
</script>
@endpush
@endsection
