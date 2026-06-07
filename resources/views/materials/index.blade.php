@php
    $isLecturer = $isLecturer ?? false;
    $materialsLayout = $isLecturer ? 'layouts.admin' : ($isRep ? 'layouts.classrep' : 'layouts.student');
@endphp
@extends($materialsLayout)

@section('title', 'Course materials')

@section('content')
@php
    $canUpload = $isRep || $isLecturer;
    $iconFor = function (string $kind) {
        return match ($kind) {
            'pdf'     => ['fa-file-pdf',         'text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-900/30'],
            'doc'     => ['fa-file-word',        'text-blue-600 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30'],
            'xls'     => ['fa-file-excel',       'text-emerald-600 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30'],
            'ppt'     => ['fa-file-powerpoint',  'text-orange-600 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/30'],
            'image'   => ['fa-file-image',       'text-pink-600 dark:text-pink-300 bg-pink-50 dark:bg-pink-900/30'],
            'archive' => ['fa-file-zipper',      'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30'],
            'audio'   => ['fa-file-audio',       'text-purple-600 dark:text-purple-300 bg-purple-50 dark:bg-purple-900/30'],
            'video'   => ['fa-file-video',       'text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30'],
            'text'    => ['fa-file-lines',       'text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800'],
            default   => ['fa-file',             'text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800'],
        };
    };
@endphp
<div class="w-full max-w-5xl mx-auto space-y-6 pb-8">
    {{-- Header --}}
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Class resources</p>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Course materials</h1>
            <p class="text-sm text-slate-600 dark:text-slate-300 max-w-xl">
                @if($isLecturer)
                    Share course outlines, slides, and study material with the classes you teach. Students download straight from their dashboard.
                @elseif($isRep)
                    Upload course outlines, lecture notes, slides, and recordings — students in your class can download them straight from their dashboard.
                @else
                    Course outlines and study materials shared by your lecturer or class rep. Tap any file to download.
                @endif
            </p>
        </div>
        @if($materialsByCourse->isNotEmpty())
            <span class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                <i class="fas fa-folder-open text-slate-400 dark:text-slate-500"></i>
                {{ $materialsByCourse->flatten(1)->count() }} file{{ $materialsByCourse->flatten(1)->count() === 1 ? '' : 's' }}
            </span>
        @endif
    </header>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 text-sm px-4 py-2.5 flex items-start gap-2">
            <i class="fas fa-circle-check mt-0.5 text-emerald-600 dark:text-emerald-300"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-200 text-sm px-4 py-2.5 flex items-start gap-2">
            <i class="fas fa-triangle-exclamation mt-0.5 text-red-600 dark:text-red-300"></i> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-200 text-sm px-4 py-2.5">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Upload card (rep or lecturer) --}}
    @if($canUpload)
        @php
            // Lecturers need a course→class map so the class dropdown can be
            // filtered to only the classes that share the picked course.
            $courseClassMapJson = isset($courseClassMap) ? $courseClassMap->toJson() : '{}';
        @endphp
        <section class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
            <header class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 flex items-center gap-2.5">
                <span class="h-8 w-8 rounded-lg bg-primary/10 dark:bg-sky-900/40 text-primary dark:text-sky-300 flex items-center justify-center">
                    <i class="fas fa-upload text-xs"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Upload material</h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">PDFs, slides, docs, zips, images, audio &amp; video — up to 30 MB.</p>
                </div>
            </header>
            @if($uploadableCourses->isEmpty())
                <div class="p-5">
                    @if($isLecturer)
                        <p class="text-sm text-slate-600 dark:text-slate-300">No courses assigned to you yet. Ask an admin to link you to courses you teach.</p>
                    @else
                        <p class="text-sm text-slate-600 dark:text-slate-300">No courses are assigned to your class yet. Ask an admin to assign courses first.</p>
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('dashboard.materials.store') }}" enctype="multipart/form-data" class="p-5 grid grid-cols-1 sm:grid-cols-12 gap-4"
                      data-course-class-map='{!! $courseClassMapJson !!}'
                      id="material-upload-form">
                    @csrf

                    <div class="sm:col-span-{{ ($isLecturer || $classes->count() > 1) ? 8 : 12 }}">
                        <label for="material_course_id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Course</label>
                        <select name="course_id" id="material_course_id" required class="w-full border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                            <option value="">Select course…</option>
                            @foreach($uploadableCourses as $course)
                                <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                    {{ $course->course_name }}@if($course->course_code) ({{ $course->course_code }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($isLecturer || $classes->count() > 1)
                        <div class="sm:col-span-4">
                            <label for="material_class_id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Class</label>
                            <select name="class_id" id="material_class_id" required class="w-full border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                <option value="">Select class…</option>
                                @foreach($classes as $cls)
                                    <option value="{{ $cls->id }}" @selected(old('class_id') == $cls->id)>{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="class_id" value="{{ $classes->first()->id ?? '' }}">
                    @endif

                    <div class="sm:col-span-12">
                        <label for="material_title" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Title</label>
                        <input type="text" name="title" id="material_title" maxlength="200" required value="{{ old('title') }}" placeholder="e.g. Course outline — Week 1"
                               class="w-full border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                    </div>

                    <div class="sm:col-span-12">
                        <label for="material_description" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span></label>
                        <textarea name="description" id="material_description" rows="2" maxlength="2000" placeholder="Notes or instructions for your classmates…"
                                  class="w-full border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">{{ old('description') }}</textarea>
                    </div>

                    <div class="sm:col-span-12">
                        <label for="material_file" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">File</label>
                        <input type="file" name="file" id="material_file" required
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.zip,.rar,.7z,.jpg,.jpeg,.png,.gif,.webp,.mp3,.mp4,.m4a,.m4v,.mov"
                               class="block w-full text-sm text-slate-700 dark:text-slate-300 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-primary file:text-white file:font-semibold hover:file:bg-primary/90">
                    </div>

                    <div class="sm:col-span-12 flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary/90">
                            <i class="fas fa-cloud-arrow-up"></i> Share with class
                        </button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    {{-- Materials grouped by course --}}
    @if($materialsByCourse->isEmpty())
        <section class="rounded-xl border border-dashed border-slate-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 px-6 py-12 text-center">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                <i class="fas fa-folder-open text-lg"></i>
            </span>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No materials shared yet</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                @if($canUpload)
                    Upload a course outline or lecture notes above to share with the class.
                @else
                    Once your lecturer or class rep uploads outlines or lecture notes, you&rsquo;ll see them here.
                @endif
            </p>
        </section>
    @else
        @foreach($materialsByCourse as $courseId => $items)
            @php
                $course = $items->first()->course;
                $courseName = $course?->course_name ?? 'Unassigned course';
                $courseCode = $course?->course_code;
            @endphp
            <section class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
                <header class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $courseName }}@if($courseCode) <span class="text-slate-500 dark:text-slate-400 font-medium">({{ $courseCode }})</span>@endif</h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $items->count() }} file{{ $items->count() === 1 ? '' : 's' }}</p>
                    </div>
                    <span class="shrink-0 h-8 w-8 rounded-lg bg-primary/10 dark:bg-sky-900/40 text-primary dark:text-sky-300 flex items-center justify-center">
                        <i class="fas fa-book text-xs"></i>
                    </span>
                </header>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($items as $material)
                        @php
                            [$iconName, $iconCls] = $iconFor($material->fileKind());
                            $uploadedByLecturer = (int) ($material->uploaded_by_lecturer_id ?? 0) > 0;
                            $uploaderName = $uploadedByLecturer
                                ? trim((string) ($material->lecturerUploader->name ?? ''))
                                : ($material->uploader
                                    ? trim((string) (($material->uploader->first_name ?? '').' '.($material->uploader->last_name ?? '')))
                                    : '');
                            $uploaderBadge = $uploadedByLecturer ? 'Lecturer' : 'Rep';
                            $uploaderBadgeClass = $uploadedByLecturer
                                ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-200 border-indigo-200 dark:border-indigo-800'
                                : 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800';
                            $canDelete = $isRep
                                || ($isLecturer && (
                                    ((int) ($material->uploaded_by_lecturer_id ?? 0) === (int) ($lecturer->id ?? 0))
                                    || ($material->course && method_exists($lecturer ?? null, 'managesCourse') && optional($lecturer)->managesCourse($material->course))
                                ));
                        @endphp
                        <li class="px-5 py-3 flex items-start gap-3 hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <span class="shrink-0 h-10 w-10 rounded-lg {{ $iconCls }} flex items-center justify-center">
                                <i class="fas {{ $iconName }} text-base"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                                    {{ $material->title }}
                                    <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wide {{ $uploaderBadgeClass }}">
                                        <i class="fas {{ $uploadedByLecturer ? 'fa-chalkboard-user' : 'fa-user-tie' }} text-[8px]"></i>
                                        {{ $uploaderBadge }}
                                    </span>
                                </p>
                                @if($material->description)
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2">{{ $material->description }}</p>
                                @endif
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-database text-slate-400 dark:text-slate-500 text-[9px]"></i>
                                        {{ $material->formattedFileSize() }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-clock text-slate-400 dark:text-slate-500 text-[9px]"></i>
                                        {{ $material->created_at?->diffForHumans() ?? '—' }}
                                    </span>
                                    @if($uploaderName !== '')
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fas fa-user text-slate-400 dark:text-slate-500 text-[9px]"></i>
                                            {{ $uploaderName }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-1.5">
                                <a href="{{ route('dashboard.materials.download', $material) }}"
                                   class="inline-flex items-center gap-1.5 rounded-md bg-primary text-white px-3 py-1.5 text-xs font-semibold hover:bg-primary/90">
                                    <i class="fas fa-download text-[10px]"></i> Download
                                </a>
                                @if($canDelete)
                                    <form method="POST" action="{{ route('dashboard.materials.destroy', $material) }}"
                                          onsubmit="return confirm('Remove this material? Students will lose access.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-200 dark:border-red-900/60 bg-white dark:bg-slate-900 text-red-700 dark:text-red-300 px-2.5 py-1.5 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-950/40">
                                            <i class="fas fa-trash text-[10px]"></i>
                                            <span class="hidden sm:inline">Remove</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('material-upload-form');
    if (!form) return;
    var raw = form.getAttribute('data-course-class-map') || '{}';
    var map = {};
    try { map = JSON.parse(raw); } catch (e) { map = {}; }
    if (!map || typeof map !== 'object' || Object.keys(map).length === 0) return;

    var courseSelect = document.getElementById('material_course_id');
    var classSelect = document.getElementById('material_class_id');
    if (!courseSelect || !classSelect) return;

    var allOptions = Array.prototype.slice.call(classSelect.options).map(function (o) {
        return { value: o.value, text: o.text };
    });

    function refresh() {
        var courseId = courseSelect.value;
        var allowed = map[courseId] || null;
        classSelect.innerHTML = '';
        var blank = document.createElement('option');
        blank.value = '';
        blank.text = 'Select class…';
        classSelect.appendChild(blank);
        allOptions.forEach(function (opt) {
            if (!opt.value) return;
            if (allowed && allowed.indexOf(parseInt(opt.value, 10)) === -1) return;
            var o = document.createElement('option');
            o.value = opt.value;
            o.text = opt.text;
            classSelect.appendChild(o);
        });
        if (classSelect.options.length === 2) {
            classSelect.value = classSelect.options[1].value;
        }
    }
    courseSelect.addEventListener('change', refresh);
    if (courseSelect.value) refresh();
})();
</script>
@endpush
@endsection
