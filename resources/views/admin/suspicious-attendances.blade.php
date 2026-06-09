@extends('layouts.admin')

@section('title', 'Suspicious Attendance')

@section('content')
{{--
    Admin review panel — non-blocking fraud flags raised by
    AttendanceRiskService during online attendance submissions. Rows
    listed here are ALREADY marked present (per spec PART 12); this
    page exists solely so a human can investigate after the fact.

    Filter pills: All flagged · Medium+ (default) · Low only · Medium only · High only.
--}}
<div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">Suspicious Attendance</h1>
        <p class="text-gray-600 text-sm mt-1">
            Online attendance submissions flagged by automated risk rules.
            <span class="text-gray-500">All rows are still marked present —
            this is post-hoc review only.</span>
        </p>
    </div>
    @if($hasRiskColumns)
        <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold">
            @php
                $pills = [
                    ['medium_plus', 'Medium+', 'sky'],
                    ['high',        'High',    'rose'],
                    ['medium',      'Medium',  'amber'],
                    ['low',         'Low',     'slate'],
                ];
            @endphp
            @foreach($pills as [$value, $label, $tone])
                @php
                    $active = $level === $value;
                    $base = $active
                        ? "bg-{$tone}-600 text-white border-{$tone}-600"
                        : "bg-white text-{$tone}-700 border-{$tone}-200 hover:bg-{$tone}-50";
                @endphp
                <a href="{{ route('dashboard.suspicious-attendances', array_filter(['level' => $value, 'session' => $session ?: null])) }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md border {{ $base }}">
                    {{ $label }}
                    @if(isset($counts[$value]))
                        <span class="text-[10px] font-normal opacity-80">({{ $counts[$value] }})</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>

@if(! $hasRiskColumns)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-semibold">Risk scoring is not yet enabled on this database.</p>
        <p class="text-xs mt-1 text-amber-800">
            Run <code class="font-mono bg-amber-100 px-1 rounded">php artisan migrate</code> to apply
            the <code class="font-mono">add_risk_columns_to_attendances_table</code> migration. Until
            then, online attendance still records normally but no flags are produced.
        </p>
    </div>
@else
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead class="bg-gray-50">
                    <tr class="text-left text-[12px] font-semibold text-gray-700">
                        <th class="px-3 py-2.5">Student</th>
                        <th class="px-3 py-2.5">Index</th>
                        <th class="px-3 py-2.5 hidden md:table-cell">Course</th>
                        <th class="px-3 py-2.5 hidden lg:table-cell">Session</th>
                        <th class="px-3 py-2.5 text-center">Risk</th>
                        <th class="px-3 py-2.5 text-center">Score</th>
                        <th class="px-3 py-2.5">Reason</th>
                        <th class="px-3 py-2.5 hidden xl:table-cell">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                        @php
                            $levelTone = [
                                'high'   => 'rose',
                                'medium' => 'amber',
                                'low'    => 'slate',
                            ][$row->risk_level] ?? 'slate';
                            $reasons = is_array($row->risk_reasons) ? $row->risk_reasons : [];
                        @endphp
                        <tr class="hover:bg-gray-50 text-sm align-top">
                            <td class="px-3 py-2.5">
                                <p class="font-semibold text-gray-900">{{ $row->student?->getDisplayName() ?: '—' }}</p>
                                @if($row->student?->schoolClass)
                                    <p class="text-[11px] text-gray-500">{{ $row->student->schoolClass->name }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 font-mono text-[12.5px] text-gray-800">{{ $row->student?->index_number ?: '—' }}</td>
                            <td class="px-3 py-2.5 hidden md:table-cell">
                                <span class="text-gray-900">{{ $row->course?->course_name ?: '—' }}</span>
                                @if($row->course?->course_code)
                                    <span class="block text-[11px] text-gray-500">{{ $row->course->course_code }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 hidden lg:table-cell">
                                @if($row->attendanceSession)
                                    <p class="text-[12px] text-gray-700">#{{ $row->attendanceSession->id }} ({{ $row->attendanceSession->mode }})</p>
                                    @if($row->attendanceSession->start_time)
                                        <p class="text-[11px] text-gray-500">{{ \Illuminate\Support\Carbon::parse($row->attendanceSession->start_time)->format('M j H:i') }}</p>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase bg-{{ $levelTone }}-100 text-{{ $levelTone }}-800">
                                    {{ $row->risk_level }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center font-mono font-semibold tabular-nums">{{ $row->risk_score ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                @if($reasons === [])
                                    <span class="text-gray-400">—</span>
                                @else
                                    <ul class="list-disc list-inside text-[12px] text-gray-700 space-y-0.5">
                                        @foreach($reasons as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-[12px] text-gray-600 hidden xl:table-cell">
                                {{ optional($row->attendance_time)->format('M j, Y H:i') ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center text-gray-500">
                                <i class="fas fa-check-circle text-2xl text-emerald-500 mb-2 block"></i>
                                Nothing flagged at this level. Online attendance is clean.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-gray-100">
            {{ $rows->links() }}
        </div>
    </div>
@endif
@endsection
