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

    <form method="GET" action="{{ route('dashboard.students.index') }}" class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 w-full">
        <div class="flex flex-col lg:flex-row lg:items-end gap-3 lg:gap-4">
            <div class="flex-1 min-w-0">
                <label for="search" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Search</label>
                <div class="relative">
                    <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="search" name="search" id="search" value="{{ request('search') }}" placeholder="Name or index…" autocomplete="off"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            @if($classes->isNotEmpty())
            <div class="w-full sm:max-w-xs lg:w-56 lg:max-w-none shrink-0">
                <label for="class_id" class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Class</label>
                <select name="class_id" id="class_id" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                    <option value="">All classes</option>
                    @foreach($classes as $c)
                    <option value="{{ $c->id }}" {{ (string) request('class_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="flex items-center gap-2 lg:ml-auto shrink-0">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2">
                    Apply
                </button>
                @if(request()->filled('search') || request()->filled('class_id'))
                <a href="{{ route('dashboard.students.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800 py-2.5">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <div class="rounded-2xl border border-slate-200/90 bg-white overflow-hidden w-full">
        <div class="max-h-[calc(100vh-260px)] overflow-y-auto overscroll-contain">
            @forelse($students as $student)
                <a href="{{ route('dashboard.students.show', $student) }}"
                   class="flex items-center gap-3 sm:gap-4 px-4 sm:px-6 py-3.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50/90 focus:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/25">
                    <span class="w-6 sm:w-8 shrink-0 text-right text-xs font-medium tabular-nums text-slate-400">{{ $loop->iteration }}</span>
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
                <div class="px-4 py-16 text-center">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fas fa-user-slash text-xl"></i>
                    </span>
                    <p class="text-sm font-semibold text-slate-700">No students match</p>
                    <p class="text-xs text-slate-500 mt-1">Try another search or class filter.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
