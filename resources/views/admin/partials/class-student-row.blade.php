@php
    $idx = strtoupper($student->index_number ?? '');
    $programClass = str_contains($idx, 'ITN') ? 'bg-sky-50 text-sky-700 ring-sky-200/80'
        : (str_contains($idx, 'ITS') ? 'bg-emerald-50 text-emerald-700 ring-emerald-200/80'
        : (str_contains($idx, 'ITD') ? 'bg-violet-50 text-violet-700 ring-violet-200/80'
        : 'bg-slate-50 text-slate-600 ring-slate-200/80'));
    $detailUrl = $detailUrl ?? route('dashboard.students.show', $student);
@endphp
<a href="{{ $detailUrl }}"
   class="group flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white p-3.5 sm:p-4 hover:border-primary/30 hover:shadow-sm transition-all ring-1 ring-slate-200/40">
    @if($student->profile_image)
        <img src="{{ $student->profileImageUrl() }}" alt="" class="h-11 w-11 rounded-full object-cover ring-1 ring-slate-200 bg-white shrink-0" loading="lazy">
    @else
        <span class="inline-flex h-11 w-11 rounded-full bg-gradient-to-br from-primary/15 to-primary/5 text-primary items-center justify-center text-xs font-bold shrink-0 ring-1 ring-primary/10">
            {{ $student->avatarInitials() }}
        </span>
    @endif
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-sm font-semibold text-slate-900 group-hover:text-primary transition-colors">{{ $student->index_number }}</span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-semibold ring-1 {{ $programClass }}">
                {{ $student->getProgramLabel() }}
            </span>
        </div>
        @if($student->getDisplayName() !== '')
            <p class="mt-0.5 text-sm text-slate-600 truncate">{{ $student->getDisplayName() }}</p>
        @else
            <p class="mt-0.5 text-sm text-slate-400 italic">Name not set</p>
        @endif
    </div>
    <span class="hidden sm:inline text-[10px] font-medium text-slate-400 tabular-nums shrink-0">#{{ $serial }}</span>
</a>
