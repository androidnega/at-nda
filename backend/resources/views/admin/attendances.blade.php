@extends('layouts.admin')

@section('title', 'Attendance Records')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">Attendance Records</h1>
    <p class="text-gray-600 text-sm mt-1">All marked attendance</p>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[400px]">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Student</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700 hidden sm:table-cell">Course</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Time</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($attendances as $a)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        @if($a->student->getDisplayName() !== '')
                            {{ $a->student->getDisplayName() }} <span class="text-gray-500">({{ $a->student->index_number }})</span>
                        @else
                            <span class="font-mono text-gray-800">{{ $a->student->index_number }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">{{ $a->course->course_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $a->attendance_time->format('M d, Y H:i') }}</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-lg text-sm">{{ $a->status }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-12 text-center text-gray-500">No attendance records yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-100">
        {{ $attendances->links() }}
    </div>
</div>
@endsection
