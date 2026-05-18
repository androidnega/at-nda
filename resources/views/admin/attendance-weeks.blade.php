@extends('layouts.admin')

@section('title', 'Attendance reset')

@section('content')
<div class="w-full max-w-none space-y-8 pb-8">
    {{-- Page header --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Term &amp; numbering</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Attendance reset</h1>
        <p class="mt-2 text-sm text-gray-600 max-w-2xl leading-relaxed">
            Seed week labels for new sessions, or wipe history when a term restarts. Session numbers and IDs realign after a reset.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <div class="min-w-[6.5rem] rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Classes</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900">{{ $stats['classes'] ?? 0 }}</p>
            </div>
            <div class="min-w-[6.5rem] rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Courses</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900">{{ $stats['courses'] ?? 0 }}</p>
            </div>
            <div class="min-w-[6.5rem] rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Week rows</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900">{{ $stats['weekRows'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 text-emerald-900 border border-emerald-200 flex items-start gap-3">
            <i class="fas fa-circle-check mt-0.5 text-emerald-600"></i>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-lg bg-red-50 text-red-900 border border-red-200 flex items-start gap-3">
            <i class="fas fa-circle-exclamation mt-0.5 text-red-600"></i>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif

    @if($courses->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-amber-200 bg-amber-50/50 p-10 text-center">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 text-amber-700 mb-4"><i class="fas fa-book"></i></span>
            <p class="text-gray-900 font-semibold">No courses yet</p>
            <p class="text-sm text-gray-600 mt-1">Create courses first, then return here to manage weeks.</p>
            <a href="{{ route('dashboard.courses.create') }}" class="inline-flex mt-4 items-center gap-2 rounded-lg bg-primary text-white px-5 py-2.5 text-sm font-medium hover:bg-primary/90">Create course</a>
        </div>
    @else
        <section aria-labelledby="week-numbering-heading" class="space-y-4">
            <h2 id="week-numbering-heading" class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Set next week number</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Single course --}}
                <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7">
                    <div class="flex items-start gap-3 mb-6">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                            <i class="fas fa-hashtag"></i>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">One course</h3>
                            <p class="text-sm text-gray-600 mt-1">The next <strong class="font-medium text-gray-800">new</strong> week row uses this number, then increases automatically. Options show the current max week.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('dashboard.attendance-weeks.next-course') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="course_next" class="block text-sm font-medium text-gray-700 mb-1.5">Course</label>
                            <select name="course_id" id="course_next" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-900 focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                @foreach($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->course_name }} @if($c->course_code)({{ $c->course_code }})@endif — max W{{ $c->maxAttendanceWeekNumber() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4 sm:items-end">
                            <div class="sm:w-32">
                                <label for="next_week_number_course" class="block text-sm font-medium text-gray-700 mb-1.5">Next week #</label>
                                <input type="number" name="next_week_number" id="next_week_number_course" required min="1" max="500" value="1"
                                    class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm font-semibold tabular-nums focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 text-white px-5 py-2.5 text-sm font-medium hover:bg-gray-800 sm:ml-auto w-full sm:w-auto">
                                <i class="fas fa-floppy-disk text-xs opacity-90"></i> Save
                            </button>
                        </div>
                    </form>
                </div>

                @if($classes->isNotEmpty())
                <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7">
                    <div class="flex items-start gap-3 mb-6">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                            <i class="fas fa-layer-group"></i>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Whole class</h3>
                            <p class="text-sm text-gray-600 mt-1">Apply the same seed to <strong class="font-medium text-gray-800">every</strong> course linked to that class.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('dashboard.attendance-weeks.next-class') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="class_next" class="block text-sm font-medium text-gray-700 mb-1.5">Class</label>
                            <select name="class_id" id="class_next" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                @foreach($classes as $cl)
                                    <option value="{{ $cl->id }}">{{ $cl->name }} @if($cl->level) · Level {{ $cl->level }}@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4 sm:items-end">
                            <div class="sm:w-32">
                                <label for="next_week_number_class" class="block text-sm font-medium text-gray-700 mb-1.5">Next week #</label>
                                <input type="number" name="next_week_number" id="next_week_number_class" required min="1" max="500" value="1"
                                    class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm font-semibold tabular-nums focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary text-white px-5 py-2.5 text-sm font-medium hover:bg-primary/90 sm:ml-auto w-full sm:w-auto">
                                <i class="fas fa-floppy-disk text-xs opacity-90"></i> Save
                            </button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </section>

        {{-- Danger zone --}}
        <section aria-labelledby="reset-heading" class="rounded-xl border border-rose-200 bg-white overflow-hidden">
            <div class="px-6 py-4 border-b border-rose-100 bg-rose-50 flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700"><i class="fas fa-triangle-exclamation"></i></span>
                <div>
                    <h2 id="reset-heading" class="font-semibold text-rose-950">Reset attendance data</h2>
                    <p class="text-sm text-rose-900/90 mt-0.5">Deletes marks, sessions, and week rows. New sessions start at <strong>week 1</strong> / <strong>session 1</strong> for cleared courses.</p>
                </div>
            </div>
            <div class="p-6 sm:p-8 space-y-8">
                <form method="POST" action="{{ route('dashboard.attendance-weeks.reset-course') }}" class="space-y-4" onsubmit="return document.getElementById('reset_confirm_course')?.checked;">
                    @csrf
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-700 text-xs font-bold">1</span>
                        Single course
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-3 lg:items-end">
                        <div class="lg:col-span-5">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Course</label>
                            <select name="course_id" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-rose-200 focus:border-rose-300">
                                @foreach($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->course_name }} @if($c->course_code)({{ $c->course_code }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-4 flex items-center">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="confirm" id="reset_confirm_course" value="1" required class="rounded border-gray-300 text-primary focus:ring-primary">
                                I understand this cannot be undone
                            </label>
                        </div>
                        <div class="lg:col-span-3">
                            <button type="submit" class="w-full lg:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 text-white px-5 py-2.5 text-sm font-medium hover:bg-rose-700">
                                Reset course
                            </button>
                        </div>
                    </div>
                </form>

                @if($classes->isNotEmpty())
                <div class="border-t border-gray-100 pt-8">
                    <form method="POST" action="{{ route('dashboard.attendance-weeks.reset-class') }}" class="space-y-4" onsubmit="return document.getElementById('reset_confirm_class')?.checked;">
                        @csrf
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-700 text-xs font-bold">2</span>
                            All courses in a class
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-3 lg:items-end">
                            <div class="lg:col-span-5">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Class</label>
                                <select name="class_id" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-rose-200 focus:border-rose-300">
                                    @foreach($classes as $cl)
                                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-4 flex items-center">
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="confirm" id="reset_confirm_class" value="1" required class="rounded border-gray-300 text-primary focus:ring-primary">
                                    I understand this cannot be undone
                                </label>
                            </div>
                            <div class="lg:col-span-3">
                                <button type="submit" class="w-full lg:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 text-white px-5 py-2.5 text-sm font-medium hover:bg-rose-700">
                                    Reset class
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                @endif

                <div class="border-t border-gray-100 pt-8">
                    <form method="POST" action="{{ route('dashboard.attendance-weeks.reset-all') }}" class="space-y-4" onsubmit="return this.querySelector('[name=confirm_reset_all]')?.value === 'RESET';">
                        @csrf
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-red-100 text-red-800 text-xs font-bold">!</span>
                            Entire system
                        </div>
                        <p class="text-sm text-gray-600">Type <kbd class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50 font-mono text-xs">RESET</kbd> to confirm — removes <strong>all</strong> attendance data for every course.</p>
                        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                            <input type="text" name="confirm_reset_all" autocomplete="off" placeholder="RESET"
                                class="flex-1 max-w-xs border-2 border-red-200 rounded-lg px-3 py-2.5 font-mono text-sm tracking-widest uppercase focus:ring-2 focus:ring-red-200 focus:border-red-400" required>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-700 text-white px-5 py-2.5 text-sm font-medium hover:bg-red-800">
                                <i class="fas fa-bomb text-xs"></i> Reset everything
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
