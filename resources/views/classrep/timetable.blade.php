@extends('layouts.classrep')

@section('title', 'Manage timetable')

@section('content')
<div class="max-w-[1400px] mx-auto space-y-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Manage class timetable</h1>
        <p class="text-gray-500 text-sm max-w-2xl">
            Build the weekly schedule for your class. Pick from the courses your class is enrolled in, assign one of the available
            lecturers, and set the day, time, and venue. Changes only affect <strong>your class</strong>.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm px-4 py-2.5">
            <i class="fas fa-circle-check mr-1.5"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-2.5">
            <i class="fas fa-triangle-exclamation mr-1.5"></i> {{ session('error') }}
        </div>
    @endif

    @if($classes->count() > 1)
        <form method="GET" action="{{ route('dashboard.timetable.manage') }}" class="bg-white border border-gray-100 rounded-xl shadow-sm px-4 py-3 flex flex-wrap items-center gap-3">
            <label for="class_picker" class="text-sm font-medium text-gray-700">Class</label>
            <select id="class_picker" name="class_id" onchange="this.form.submit()" class="border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected($c->id === $selectedClass->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    {{-- Bulk import (paste text or upload .txt / .docx) --}}
    <section class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
        <details class="group">
            <summary class="cursor-pointer list-none px-5 py-4 flex flex-wrap items-center gap-3 hover:bg-gray-50">
                <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                    <i class="fas fa-wand-magic-sparkles"></i>
                </span>
                <div class="flex-1 min-w-[200px]">
                    <h2 class="text-base font-semibold text-gray-900">Bulk import from text</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Paste your weekly schedule or upload a .txt / .docx and the system will create the slots automatically.</p>
                </div>
                <a href="{{ route('dashboard.timetable.bulk-template') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                   onclick="event.stopPropagation();">
                    <i class="fas fa-download text-[11px]"></i> Download template
                </a>
                <span class="text-[11px] text-primary inline-flex items-center gap-1 group-open:rotate-180 transition">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </summary>

            <div class="border-t border-gray-100 px-5 py-4 bg-gray-50/40">
                @if(session('import_messages'))
                    <details class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        <summary class="cursor-pointer font-semibold">Import details ({{ count(session('import_messages')) }})</summary>
                        <ul class="mt-2 space-y-1 list-disc pl-5">
                            @foreach(session('import_messages') as $msg)
                                <li>{{ $msg }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                <form method="POST" action="{{ route('dashboard.timetable.bulk-import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Paste timetable text</label>
                        <textarea name="pasted_text" rows="10"
                            placeholder="MONDAY&#10;9AM - 11AM&#10;BIT236 - SOFTWARE ENGINEERING&#10;MR. HILARY ACKAH-ARTHUR&#10;VENUE: OBTF 7&#10;&#10;TUESDAY&#10;1PM - 3PM&#10;DTM202 - PRINCIPLES OF MANAGEMENT&#10;MR. AMOS KWASI AMOFA&#10;VENUE: OBFF 6"
                            class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-primary/25 focus:border-primary">{{ old('pasted_text') }}</textarea>
                        @error('pasted_text') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold">or</span>
                        <input type="file" name="file" accept=".txt,.docx,text/plain,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="text-xs border border-gray-200 rounded-lg file:mr-2 file:py-1.5 file:px-2 file:text-xs file:rounded-md file:border-0 file:bg-gray-100 file:font-semibold">
                        @error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <p class="text-[11px] text-gray-500">
                            Only courses already linked to <strong>{{ $selectedClass->name }}</strong> can be imported. If a course is already on this class's timetable, its day, time, lecturer and venue are <strong>overwritten</strong> with the new entry — attendance and other class data are not touched. Lecturers and venues are matched by name (honorifics like MR. / DR. are ignored).
                        </p>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary text-white px-4 py-2 text-xs font-semibold hover:bg-primary/90">
                            <i class="fas fa-bolt text-[11px]"></i> Generate timetable
                        </button>
                    </div>
                </form>
            </div>
        </details>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Add slot form --}}
        <section class="lg:col-span-2 bg-white border border-gray-100 rounded-xl shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center"><i class="fas fa-circle-plus"></i></span>
                <h2 class="text-base font-semibold text-gray-900">Add slot</h2>
            </div>

            @if($availableCourses->isEmpty())
                <p class="text-sm text-gray-600">No courses are assigned to this class yet. Ask admin to assign courses first.</p>
            @else
                <form method="POST" action="{{ route('dashboard.timetable.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Course</label>
                        <select name="course_id" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            <option value="">Select course…</option>
                            @foreach($availableCourses as $course)
                                <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                    {{ $course->course_name }}@if($course->course_code) ({{ $course->course_code }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('course_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Day</label>
                        <select name="day_of_week" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            <option value="">Select day…</option>
                            @foreach($days as $day)
                                <option value="{{ $day }}" @selected(old('day_of_week') === $day)>{{ $day }}</option>
                            @endforeach
                        </select>
                        @error('day_of_week') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Start</label>
                            <input type="time" name="start_time" value="{{ old('start_time') }}" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            @error('start_time') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">End</label>
                            <input type="time" name="end_time" value="{{ old('end_time') }}" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            @error('end_time') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Lecturer</label>
                        <select name="lecturer_id" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            <option value="">— Not assigned —</option>
                            @foreach($availableLecturers as $lec)
                                <option value="{{ $lec->id }}" @selected(old('lecturer_id') == $lec->id)>{{ $lec->name }}</option>
                            @endforeach
                        </select>
                        @error('lecturer_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        <p class="text-[11px] text-gray-500 mt-1">Pick from admin-created lecturers. The same lecturer can be assigned to multiple classes.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Venue</label>
                        @if($availableVenues->isEmpty())
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
                                No venues exist yet. Ask an admin to create lecture halls under <strong>Venues</strong> before saving a slot.
                            </div>
                        @else
                            <select name="venue_id" required class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                <option value="">— Choose venue —</option>
                                @foreach($availableVenues as $venue)
                                    <option value="{{ $venue->id }}" @selected(old('venue_id') == $venue->id)>{{ $venue->name }}{{ $venue->code ? ' ('.$venue->code.')' : '' }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">Reps can only pick from the lecture halls admin has registered — they can&rsquo;t create new ones here.</p>
                        @endif
                        @error('venue_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary text-white px-4 py-2.5 text-sm font-semibold hover:bg-primary/90 disabled:opacity-50" @disabled($availableVenues->isEmpty())>
                        <i class="fas fa-floppy-disk text-xs"></i> Save slot
                    </button>
                </form>
            @endif
        </section>

        {{-- Existing slots --}}
        <section class="lg:col-span-3 space-y-4">
            <div class="bg-white border border-gray-100 rounded-xl shadow-sm">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">Current slots — {{ $selectedClass->name }}</h2>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-primary/90 bg-primary/10 px-2 py-0.5 rounded-md tabular-nums">{{ $entries->count() }}</span>
                </div>

                @if($entries->isEmpty())
                    <div class="px-4 py-8 text-center">
                        <p class="text-sm text-gray-600">No slots yet. Add your class's first lecture using the form on the left.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($entries as $entry)
                            <details class="group">
                                <summary class="cursor-pointer list-none px-4 py-3 flex flex-wrap items-center gap-3 hover:bg-gray-50">
                                    <div class="flex flex-col flex-1 min-w-[180px]">
                                        <span class="text-sm font-semibold text-gray-900">{{ $entry->course?->course_name ?? '—' }}
                                            @if($entry->course?->course_code)
                                                <span class="text-gray-500 font-medium">({{ $entry->course->course_code }})</span>
                                            @endif
                                        </span>
                                        <span class="text-[11px] text-gray-500 mt-0.5">
                                            <i class="fas fa-chalkboard-user mr-1 text-gray-400"></i>
                                            {{ $entry->resolvedLecturerName() ?: '—' }}
                                        </span>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-primary tabular-nums">
                                        <i class="fas fa-clock text-[10px] opacity-80"></i>
                                        {{ $entry->day_of_week }} · {{ \Carbon\Carbon::parse($entry->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($entry->end_time)->format('H:i') }}
                                    </span>
                                    <span class="text-[11px] text-gray-500 inline-flex items-center gap-1">
                                        <i class="fas fa-location-dot text-gray-400"></i>
                                        {{ $entry->resolvedVenueName() ?: '—' }}
                                    </span>
                                    <span class="ml-auto text-[11px] text-primary group-open:rotate-180 transition">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </summary>
                                <div class="px-4 pb-4 bg-gray-50/60">
                                    <form method="POST" action="{{ route('dashboard.timetable.update', $entry) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3 pt-3">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="class_id" value="{{ $entry->class_id }}">
                                        <div class="sm:col-span-3">
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Course</label>
                                            <select name="course_id" required class="w-full border-2 border-gray-200 rounded-lg px-2.5 py-2 text-xs focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                                @foreach($availableCourses as $course)
                                                    <option value="{{ $course->id }}" @selected($course->id === $entry->course_id)>{{ $course->course_name }}@if($course->course_code) ({{ $course->course_code }})@endif</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Lecturer</label>
                                            <select name="lecturer_id" class="w-full border-2 border-gray-200 rounded-lg px-2.5 py-2 text-xs focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                                <option value="">— Not assigned —</option>
                                                @foreach($availableLecturers as $lec)
                                                    <option value="{{ $lec->id }}" @selected($lec->id === $entry->lecturer_id)>{{ $lec->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Day</label>
                                            <select name="day_of_week" required class="w-full border-2 border-gray-200 rounded-lg px-2.5 py-2 text-xs focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                                @foreach($days as $day)
                                                    <option value="{{ $day }}" @selected($entry->day_of_week === $day)>{{ $day }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Start</label>
                                            <input type="time" name="start_time" value="{{ \Carbon\Carbon::parse($entry->start_time)->format('H:i') }}" required class="w-full border-2 border-gray-200 rounded-lg px-2.5 py-2 text-xs focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">End</label>
                                            <input type="time" name="end_time" value="{{ \Carbon\Carbon::parse($entry->end_time)->format('H:i') }}" required class="w-full border-2 border-gray-200 rounded-lg px-2.5 py-2 text-xs focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                        </div>
                                        <div class="sm:col-span-6">
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Venue</label>
                                            <select name="venue_id" required class="w-full border-2 border-gray-200 rounded-lg px-2.5 py-2 text-xs focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                                <option value="">— Choose venue —</option>
                                                @foreach($availableVenues as $venue)
                                                    <option value="{{ $venue->id }}" @selected($venue->id === $entry->venue_id)>{{ $venue->name }}{{ $venue->code ? ' ('.$venue->code.')' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-6 flex items-center gap-2 mt-1">
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary text-white px-3 py-2 text-xs font-semibold hover:bg-primary/90">
                                                <i class="fas fa-floppy-disk text-[10px]"></i> Update
                                            </button>
                                            <button type="button" onclick="document.getElementById('delete-form-{{ $entry->id }}').requestSubmit()" class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 text-red-700 px-3 py-2 text-xs font-semibold hover:bg-red-100 border border-red-100">
                                                <i class="fas fa-trash text-[10px]"></i> Remove
                                            </button>
                                        </div>
                                    </form>
                                    <form id="delete-form-{{ $entry->id }}" method="POST" action="{{ route('dashboard.timetable.destroy', $entry) }}" onsubmit="return confirm('Remove this slot from your class timetable?')" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>

<script>
    (function () {
        const syncForm = (form) => {
            const start = form.querySelector('input[name="start_time"]');
            const end = form.querySelector('input[name="end_time"]');
            if (!start || !end) return;
            if (start.value) {
                end.min = start.value;
                if (end.value && end.value <= start.value) {
                    end.setCustomValidity('End time must be after start time.');
                } else {
                    end.setCustomValidity('');
                }
            } else {
                end.removeAttribute('min');
                end.setCustomValidity('');
            }
        };

        document.querySelectorAll('form').forEach((form) => {
            const start = form.querySelector('input[name="start_time"]');
            const end = form.querySelector('input[name="end_time"]');
            if (!start || !end) return;
            start.addEventListener('change', () => syncForm(form));
            end.addEventListener('input', () => syncForm(form));
            syncForm(form);
        });
    })();
</script>
@endsection
