<a href="{{ route('dashboard.students.show', $student) }}" class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 hover:border-primary/30 hover:bg-gray-50/50 group">
    <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary font-semibold text-xs flex-shrink-0">
        {{ $student->first_name && $student->last_name ? strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) : strtoupper(substr($student->index_number ?? '', 0, 2)) }}
    </span>
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1 flex-wrap">
            <span class="font-medium text-gray-800 text-sm truncate">{{ $student->index_number }}</span>
            @if($student->isRep())
            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700">Rep</span>
            @endif
        </div>
        @if($student->getDisplayName() !== '')
            <p class="text-xs text-gray-500 truncate">{{ $student->getDisplayName() }}</p>
        @endif
        <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium {{ str_starts_with($student->index_number ?? '', 'ITN') ? 'bg-blue-100 text-blue-800' : (str_starts_with($student->index_number ?? '', 'ITS') ? 'bg-green-100 text-green-800' : (str_starts_with($student->index_number ?? '', 'ITD') ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-600')) }}">
            {{ $student->getProgramLabel() }}
        </span>
    </div>
    <i class="fas fa-chevron-right text-gray-300 group-hover:text-primary text-xs"></i>
</a>
