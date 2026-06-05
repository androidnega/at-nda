@extends('layouts.classrep')

@section('title', 'Students')

@section('content')
<div class="w-full min-w-0 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Students</h1>
            <p class="text-sm text-slate-500 mt-1">People you can view in your class groups</p>
        </div>
        <div class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold tabular-nums text-slate-700 shrink-0">
            {{ $students->count() }} <span class="text-slate-400 font-normal ml-1">total</span>
        </div>
    </div>

    @if (session('success'))
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-emerald-900">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600"><i class="fas fa-check text-sm"></i></span>
            <p class="text-sm font-medium leading-snug pt-1">{{ session('success') }}</p>
        </div>
    @endif
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600"><i class="fas fa-triangle-exclamation text-sm"></i></span>
            <ul class="text-sm space-y-0.5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Roster upload (rep only) --}}
    @if($classes->isNotEmpty())
        <details class="rounded-2xl border border-slate-200/90 bg-white overflow-hidden">
            <summary class="px-4 sm:px-5 py-3.5 cursor-pointer list-none flex items-center justify-between gap-3 hover:bg-slate-50/60">
                <div class="flex items-center gap-2.5">
                    <span class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <i class="fas fa-file-import text-xs"></i>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Upload class roster</p>
                        <p class="text-[11px] text-slate-500">Excel / CSV with index numbers. Existing students update in place; new ones are added.</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-primary">Open</span>
            </summary>
            <form action="{{ route('dashboard.rep.students.import') }}" method="POST" enctype="multipart/form-data"
                  class="px-4 sm:px-5 pb-4 pt-1 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-12 gap-3">
                @csrf
                @if($classes->count() > 1)
                    <div class="sm:col-span-5">
                        <label for="roster_class_id" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Target class</label>
                        <select name="class_id" id="roster_class_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ (string) request('class_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-5">
                @else
                    <input type="hidden" name="class_id" value="{{ $classes->first()->id }}">
                    <div class="sm:col-span-9">
                @endif
                        <label for="roster_file" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">File</label>
                        <input type="file" name="file" id="roster_file" required accept=".xlsx,.xls,.csv"
                               class="block w-full text-sm text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-primary file:text-white file:font-semibold hover:file:bg-primary/90">
                    </div>
                <div class="sm:col-span-3 flex items-end">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary text-white px-4 py-2.5 text-sm font-semibold hover:bg-primary/90">
                        <i class="fas fa-cloud-arrow-up text-xs"></i> Upload
                    </button>
                </div>
                <p class="sm:col-span-12 text-[11px] text-slate-500 leading-relaxed">
                    Required column: <code class="font-mono text-slate-700">index_number</code>. Optional: <code class="font-mono text-slate-700">first_name</code>, <code class="font-mono text-slate-700">middle_name</code>, <code class="font-mono text-slate-700">last_name</code>.
                    <a href="{{ asset('sample_students.xlsx') }}" download class="text-primary hover:underline">Download sample template</a>.
                </p>
            </form>
        </details>
    @endif

    {{-- Search + class filter. Filtering happens entirely on the client
         (no page reload) because the whole class roster is already in the
         DOM, so reps see matches as they type. --}}
    <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 w-full">
        <div class="flex flex-col lg:flex-row lg:items-end gap-3 lg:gap-4">
            <div class="flex-1 min-w-0">
                <label for="rep-student-search" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Search</label>
                <div class="relative">
                    <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="search" id="rep-student-search" placeholder="Type a name or index — matches as you type" autocomplete="off"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            @if($classes->isNotEmpty() && $classes->count() > 1)
            <div class="w-full sm:max-w-xs lg:w-56 lg:max-w-none shrink-0">
                <label for="rep-class-filter" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Class</label>
                <select id="rep-class-filter" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                    <option value="">All classes</option>
                    @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="flex items-center gap-3 lg:ml-auto shrink-0 text-xs text-slate-500">
                <span id="rep-student-count" class="tabular-nums">{{ $students->count() }} of {{ $students->count() }}</span>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/90 bg-white overflow-hidden w-full">
        <div class="max-h-[calc(100vh-260px)] overflow-y-auto overscroll-contain" id="rep-student-list">
            @forelse($students as $student)
                @php
                    $haystack = trim(($student->getDisplayName() ?? '').' '.($student->index_number ?? '').' '.($student->schoolClass?->name ?? ''));
                @endphp
                <a href="{{ route('dashboard.students.show', $student) }}"
                   data-rep-student
                   data-class-id="{{ (int) ($student->class_id ?? 0) }}"
                   data-haystack="{{ Str::lower($haystack) }}"
                   class="flex items-center gap-3 sm:gap-4 px-4 sm:px-6 py-3.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50/90 focus:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/25">
                    <span class="w-6 sm:w-8 shrink-0 text-right text-xs font-medium tabular-nums text-slate-400" data-rep-serial>{{ $loop->iteration }}</span>
                    <span class="shrink-0">
                        @if($student->profile_image)
                            <img src="{{ $student->profileImageUrl() }}" alt="" class="h-11 w-11 rounded-full object-cover border border-slate-200 bg-slate-50" loading="lazy">
                        @else
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">{{ $student->avatarInitials() }}</span>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        @if($student->getDisplayName() !== '')
                            <p class="text-sm sm:text-base font-semibold text-slate-900 truncate">{{ $student->getDisplayName() }}</p>
                        @endif
                        <p class="text-xs sm:text-sm font-mono text-slate-600 mt-0.5 truncate">{{ $student->index_number }}</p>
                        @if($student->schoolClass)
                            <p class="text-xs text-slate-500 mt-1 truncate">{{ $student->schoolClass->name }}</p>
                        @endif
                    </div>
                </a>
            @empty
                <div class="px-4 py-16 text-center" data-rep-empty-initial>
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fas fa-user-slash text-xl"></i>
                    </span>
                    <p class="text-sm font-semibold text-slate-700">No students in your classes yet</p>
                    <p class="text-xs text-slate-500 mt-1">Upload a roster above to get started.</p>
                </div>
            @endforelse
            <div id="rep-student-empty" class="hidden px-4 py-16 text-center">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                    <i class="fas fa-user-slash text-xl"></i>
                </span>
                <p class="text-sm font-semibold text-slate-700">No matches</p>
                <p class="text-xs text-slate-500 mt-1">Try a different search or class filter.</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var input = document.getElementById('rep-student-search');
    var classSel = document.getElementById('rep-class-filter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-rep-student]'));
    var emptyEl = document.getElementById('rep-student-empty');
    var countEl = document.getElementById('rep-student-count');
    var total = rows.length;

    function apply() {
        var term = (input && input.value || '').trim().toLowerCase();
        var classId = (classSel && classSel.value || '').trim();
        var shown = 0;
        var serial = 1;
        rows.forEach(function(row) {
            var hay = row.dataset.haystack || '';
            var rowClass = row.dataset.classId || '';
            var match = (term === '' || hay.indexOf(term) !== -1)
                && (classId === '' || rowClass === classId);
            row.classList.toggle('hidden', !match);
            if (match) {
                shown += 1;
                var s = row.querySelector('[data-rep-serial]');
                if (s) s.textContent = serial;
                serial += 1;
            }
        });
        if (emptyEl) emptyEl.classList.toggle('hidden', shown !== 0);
        if (countEl) countEl.textContent = shown + ' of ' + total;
    }

    if (input) input.addEventListener('input', apply);
    if (classSel) classSel.addEventListener('change', apply);
})();
</script>
@endpush
@endsection
