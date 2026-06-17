<div class="space-y-4" id="courses-list-items">
    @forelse($courses as $course)
    @php
        $classNames = $course->relationLoaded('schoolClasses') && $course->schoolClasses->isNotEmpty()
            ? $course->schoolClasses->pluck('name')->join(', ')
            : ($course->schoolClass?->name ?? '');
        $searchBlob = strtolower(trim(implode(' ', array_filter([
            $course->course_name,
            $course->course_code,
            $classNames,
            $course->qualificationLabel(),
        ]))));
    @endphp
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden course-row" data-search="{{ $searchBlob }}">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-gray-800">{{ $course->course_name }}{{ $course->course_code ? ' (' . $course->course_code . ')' : '' }}</h3>
                    @php
                        $courseQualLabel = $course->qualificationLabel();
                        $courseQualKey = strtolower(trim((string) ($course->qualification ?? '')));
                        $courseQualBg = match ($courseQualKey) {
                            'hnd' => 'bg-emerald-100 text-emerald-800',
                            'diploma' => 'bg-amber-100 text-amber-800',
                            'degree' => 'bg-indigo-100 text-indigo-800',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    @if($courseQualLabel)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide {{ $courseQualBg }}">{{ $courseQualLabel }}</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-slate-100 text-slate-600" title="No qualification filter — every class can take this course">All</span>
                    @endif
                </div>
                @if($classNames)
                <p class="text-xs text-gray-500 mt-1"><i class="fas fa-layer-group text-gray-400 mr-1"></i>{{ $classNames }}</p>
                @endif
                <p class="text-[11px] text-gray-400 mt-1 italic">Day, time, lecturer &amp; venue are set per class by each rep.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('web.attendance.form', $course) }}" target="_blank" class="text-blue-600 hover:underline text-sm">Attendance</a>
                <a href="{{ route('dashboard.pdf.export', $course) }}" target="_blank" class="text-gray-600 hover:underline text-sm">PDF</a>
                <a href="{{ route('dashboard.courses.edit', $course) }}" class="text-gray-600 hover:underline text-sm">Edit</a>
                <form action="{{ route('dashboard.courses.destroy', $course) }}" method="POST" class="inline" onsubmit="return confirm('Delete this course?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="rounded-xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500" id="courses-empty-state">
        @if(request()->filled('q'))
            No courses match “{{ request('q') }}”.
        @else
            No courses yet. Create one to get started.
        @endif
    </div>
    @endforelse
</div>

@if($courses->hasPages())
<div class="mt-4" id="courses-pagination">
    {{ $courses->links() }}
</div>
@else
<div class="mt-4 hidden" id="courses-pagination"></div>
@endif
