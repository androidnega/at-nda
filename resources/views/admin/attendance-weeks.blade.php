@extends('layouts.admin')

@section('title', 'Attendance reset')

@section('content')
<div class="w-full max-w-3xl mx-auto space-y-6 pb-10">
    {{-- Header --}}
    <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Attendance</p>
        <h1 class="text-2xl font-bold text-gray-900">Reset attendance</h1>
        <p class="text-sm text-gray-600 leading-relaxed">
            Clear the weeks, sessions, and marks recorded for a single class &amp; course, every course in a class, or the whole system. Cleared courses restart from <strong>Week&nbsp;1</strong>.
        </p>
    </header>

    {{-- At-a-glance counters --}}
    <div class="grid grid-cols-3 gap-2 sm:gap-3">
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Classes</p>
            <p class="text-xl font-bold tabular-nums text-gray-900">{{ $stats['classes'] ?? 0 }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Courses</p>
            <p class="text-xl font-bold tabular-nums text-gray-900">{{ $stats['courses'] ?? 0 }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Week rows</p>
            <p class="text-xl font-bold tabular-nums text-gray-900">{{ $stats['weekRows'] ?? 0 }}</p>
        </div>
    </div>

    @if (session('success'))
        <div class="p-3.5 rounded-lg bg-emerald-50 text-emerald-900 border border-emerald-200 flex items-start gap-2.5">
            <i class="fas fa-circle-check mt-0.5 text-emerald-600"></i>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="p-3.5 rounded-lg bg-red-50 text-red-900 border border-red-200 flex items-start gap-2.5">
            <i class="fas fa-circle-exclamation mt-0.5 text-red-600"></i>
            <span class="text-sm font-medium">{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="p-3.5 rounded-lg bg-red-50 text-red-900 border border-red-200">
            <ul class="text-sm list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($courses->isEmpty())
        <div class="rounded-lg border border-dashed border-amber-200 bg-amber-50/60 p-8 text-center">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700 mb-3"><i class="fas fa-book"></i></span>
            <p class="text-gray-900 font-semibold">No courses yet</p>
            <p class="text-sm text-gray-600 mt-1">Create courses first, then come back here to reset data.</p>
            <a href="{{ route('dashboard.courses.create') }}" class="inline-flex mt-3 items-center gap-2 rounded-lg bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90">Create course</a>
        </div>
    @else
        @php
            // Map<course_id, class_id[]> for client-side filtering of the course
            // dropdown when an admin picks a class first.
            $courseClassJson = $courseClassMap->toJson();
        @endphp

        {{-- Card 1: Reset one class + one course --}}
        <section class="rounded-xl border border-rose-200 bg-white overflow-hidden">
            <header class="px-5 py-3.5 border-b border-rose-100 bg-rose-50">
                <h2 class="font-semibold text-rose-950 flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-rose-100 text-rose-700 text-xs font-bold">1</span>
                    Reset one class &amp; course
                </h2>
                <p class="text-xs text-rose-900/80 mt-1 ml-9">Pick a class first, then choose one of its courses. Only that class's attendance is wiped.</p>
            </header>
            <form method="POST" action="{{ route('dashboard.attendance-weeks.reset-course') }}"
                  data-reset-form
                  class="p-5 space-y-4"
                  onsubmit="return this.querySelector('[name=confirm]').checked;">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="reset_class_id" class="block text-sm font-medium text-gray-700 mb-1.5">Class</label>
                        <select name="class_id" id="reset_class_id" required data-class-picker
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-rose-200 focus:border-rose-300">
                            <option value="">Choose class…</option>
                            @foreach($classes as $cl)
                                <option value="{{ $cl->id }}">{{ $cl->name }}@if($cl->level) · Level {{ $cl->level }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reset_course_id" class="block text-sm font-medium text-gray-700 mb-1.5">Course</label>
                        <select name="course_id" id="reset_course_id" required data-course-picker disabled
                                class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-rose-200 focus:border-rose-300 disabled:bg-gray-50 disabled:text-gray-400">
                            <option value="">Choose a class first…</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->course_name }}@if($c->course_code) ({{ $c->course_code }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-start gap-2.5 text-sm text-gray-700 select-none">
                    <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                    <span>I understand attendance data for this class &amp; course will be permanently deleted.</span>
                </label>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-rose-700">
                        <i class="fas fa-eraser text-xs"></i> Reset this class &amp; course
                    </button>
                </div>
            </form>
        </section>

        {{-- Card 2: Reset all courses in a class --}}
        @if($classes->isNotEmpty())
        <section class="rounded-xl border border-amber-200 bg-white overflow-hidden">
            <header class="px-5 py-3.5 border-b border-amber-100 bg-amber-50">
                <h2 class="font-semibold text-amber-950 flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-amber-100 text-amber-800 text-xs font-bold">2</span>
                    Reset a whole class
                </h2>
                <p class="text-xs text-amber-900/80 mt-1 ml-9">Wipes attendance for <strong>every</strong> course in the chosen class.</p>
            </header>
            <form method="POST" action="{{ route('dashboard.attendance-weeks.reset-class') }}"
                  class="p-5 space-y-4"
                  onsubmit="return this.querySelector('[name=confirm]').checked;">
                @csrf
                <div>
                    <label for="reset_whole_class_id" class="block text-sm font-medium text-gray-700 mb-1.5">Class</label>
                    <select name="class_id" id="reset_whole_class_id" required
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-amber-200 focus:border-amber-300">
                        <option value="">Choose class…</option>
                        @foreach($classes as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}@if($cl->level) · Level {{ $cl->level }}@endif</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-start gap-2.5 text-sm text-gray-700 select-none">
                    <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <span>I understand all attendance for this class will be permanently deleted.</span>
                </label>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-amber-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-amber-700">
                        <i class="fas fa-eraser text-xs"></i> Reset whole class
                    </button>
                </div>
            </form>
        </section>
        @endif

        {{-- Card 3: Reset everything --}}
        <section class="rounded-xl border border-red-300 bg-white overflow-hidden">
            <header class="px-5 py-3.5 border-b border-red-200 bg-red-50">
                <h2 class="font-semibold text-red-900 flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-red-100 text-red-800 text-xs font-bold">!</span>
                    Reset the entire system
                </h2>
                <p class="text-xs text-red-900/80 mt-1 ml-9">Removes <strong>all</strong> attendance for every class and every course. Cannot be undone.</p>
            </header>
            <form method="POST" action="{{ route('dashboard.attendance-weeks.reset-all') }}"
                  class="p-5 space-y-4"
                  onsubmit="return this.querySelector('[name=confirm_reset_all]').value === 'RESET';">
                @csrf
                <div>
                    <label for="reset_everything" class="block text-sm font-medium text-gray-700 mb-1.5">Type <span class="font-mono text-red-700">RESET</span> to confirm</label>
                    <input type="text" name="confirm_reset_all" id="reset_everything" autocomplete="off" placeholder="RESET" required
                           class="w-full max-w-xs border-2 border-red-200 rounded-lg px-3 py-2.5 font-mono text-sm tracking-widest uppercase focus:ring-2 focus:ring-red-200 focus:border-red-400">
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-700 text-white px-5 py-2.5 text-sm font-semibold hover:bg-red-800">
                        <i class="fas fa-bomb text-xs"></i> Reset everything
                    </button>
                </div>
            </form>
        </section>

        {{-- Optional: advanced "set next week" controls, tucked away --}}
        <details class="rounded-xl border border-gray-200 bg-white">
            <summary class="px-5 py-3.5 cursor-pointer text-sm font-medium text-gray-700 select-none flex items-center justify-between">
                <span class="flex items-center gap-2"><i class="fas fa-sliders text-gray-400 text-xs"></i> Advanced: set next week number</span>
                <span class="text-xs text-gray-400">optional</span>
            </summary>
            <div class="px-5 pb-5 pt-1 space-y-4 border-t border-gray-100">
                <p class="text-xs text-gray-500">Only needed if the next session should start at a specific week (e.g. mid-semester import).</p>
                <form method="POST" action="{{ route('dashboard.attendance-weeks.next-course') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 sm:items-end">
                    @csrf
                    <div class="sm:col-span-7">
                        <label for="course_next" class="block text-xs font-medium text-gray-700 mb-1">Course</label>
                        <select name="course_id" id="course_next" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->course_name }}@if($c->course_code) ({{ $c->course_code }})@endif — max W{{ $c->maxAttendanceWeekNumber() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label for="next_week_number_course" class="block text-xs font-medium text-gray-700 mb-1">Next week #</label>
                        <input type="number" name="next_week_number" id="next_week_number_course" required min="1" max="500" value="1"
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold tabular-nums focus:ring-2 focus:ring-primary/25 focus:border-primary">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 text-white px-4 py-2 text-sm font-medium hover:bg-gray-800">
                            <i class="fas fa-floppy-disk text-xs"></i> Save
                        </button>
                    </div>
                </form>

                @if($classes->isNotEmpty())
                <form method="POST" action="{{ route('dashboard.attendance-weeks.next-class') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 sm:items-end">
                    @csrf
                    <div class="sm:col-span-7">
                        <label for="class_next" class="block text-xs font-medium text-gray-700 mb-1">Class (every course in it)</label>
                        <select name="class_id" id="class_next" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            @foreach($classes as $cl)
                                <option value="{{ $cl->id }}">{{ $cl->name }}@if($cl->level) · Level {{ $cl->level }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label for="next_week_number_class" class="block text-xs font-medium text-gray-700 mb-1">Next week #</label>
                        <input type="number" name="next_week_number" id="next_week_number_class" required min="1" max="500" value="1"
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold tabular-nums focus:ring-2 focus:ring-primary/25 focus:border-primary">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90">
                            <i class="fas fa-floppy-disk text-xs"></i> Save
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </details>

        <script>
            // Filter the course dropdown by the selected class on the
            // "Reset one class & course" form.
            (function () {
                const map = @json($courseClassMap);
                document.querySelectorAll('form[data-reset-form]').forEach(function (form) {
                    const classPicker = form.querySelector('[data-class-picker]');
                    const coursePicker = form.querySelector('[data-course-picker]');
                    if (!classPicker || !coursePicker) return;

                    // Cache original options so we can rebuild them on each change.
                    const originals = Array.from(coursePicker.querySelectorAll('option')).map(function (opt) {
                        return { value: opt.value, label: opt.textContent };
                    });

                    function rebuild() {
                        const cid = parseInt(classPicker.value, 10);
                        coursePicker.innerHTML = '';
                        if (!cid) {
                            const placeholder = document.createElement('option');
                            placeholder.value = '';
                            placeholder.textContent = 'Choose a class first…';
                            coursePicker.appendChild(placeholder);
                            coursePicker.disabled = true;
                            return;
                        }
                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Choose course…';
                        coursePicker.appendChild(placeholder);

                        let added = 0;
                        originals.forEach(function (o) {
                            if (!o.value) return;
                            const courseId = parseInt(o.value, 10);
                            const classIds = map[courseId] || [];
                            if (classIds.indexOf(cid) !== -1) {
                                const opt = document.createElement('option');
                                opt.value = o.value;
                                opt.textContent = o.label;
                                coursePicker.appendChild(opt);
                                added += 1;
                            }
                        });

                        if (added === 0) {
                            const empty = document.createElement('option');
                            empty.value = '';
                            empty.textContent = 'No courses linked to this class';
                            coursePicker.innerHTML = '';
                            coursePicker.appendChild(empty);
                            coursePicker.disabled = true;
                        } else {
                            coursePicker.disabled = false;
                        }
                    }

                    classPicker.addEventListener('change', rebuild);
                    rebuild();
                });
            })();
        </script>
    @endif
</div>
@endsection
