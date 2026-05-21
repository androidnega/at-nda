@extends($isRep ? 'layouts.classrep' : 'layouts.student')

@section('title', 'Course materials')

@section('content')
@php
    $iconFor = function (string $kind) {
        return match ($kind) {
            'pdf' => ['fa-file-pdf', 'text-red-600 bg-red-50'],
            'doc' => ['fa-file-word', 'text-blue-600 bg-blue-50'],
            'xls' => ['fa-file-excel', 'text-emerald-600 bg-emerald-50'],
            'ppt' => ['fa-file-powerpoint', 'text-orange-600 bg-orange-50'],
            'image' => ['fa-file-image', 'text-pink-600 bg-pink-50'],
            'archive' => ['fa-file-zipper', 'text-amber-700 bg-amber-50'],
            'audio' => ['fa-file-audio', 'text-purple-600 bg-purple-50'],
            'video' => ['fa-file-video', 'text-indigo-600 bg-indigo-50'],
            'text' => ['fa-file-lines', 'text-slate-600 bg-slate-50'],
            default => ['fa-file', 'text-slate-600 bg-slate-50'],
        };
    };
@endphp
<div class="w-full max-w-5xl mx-auto space-y-6 pb-8">
    {{-- Header --}}
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Class resources</p>
            <h1 class="text-2xl font-bold text-slate-900">Course materials</h1>
            <p class="text-sm text-slate-600 max-w-xl">
                @if($isRep)
                    Upload course outlines, lecture notes, slides, and recordings — students in your class can download them straight from their dashboard.
                @else
                    Course outlines and study materials shared by your class rep. Tap any file to download.
                @endif
            </p>
        </div>
        @if($materialsByCourse->isNotEmpty())
            <span class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">
                <i class="fas fa-folder-open text-slate-400"></i>
                {{ $materialsByCourse->flatten(1)->count() }} file{{ $materialsByCourse->flatten(1)->count() === 1 ? '' : 's' }}
            </span>
        @endif
    </header>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm px-4 py-2.5 flex items-start gap-2">
            <i class="fas fa-circle-check mt-0.5 text-emerald-600"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-2.5 flex items-start gap-2">
            <i class="fas fa-triangle-exclamation mt-0.5 text-red-600"></i> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-2.5">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Rep-only upload card --}}
    @if($isRep)
        <section class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <header class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/70 flex items-center gap-2.5">
                <span class="h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                    <i class="fas fa-upload text-xs"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Upload material</h2>
                    <p class="text-[11px] text-slate-500">PDFs, slides, docs, zips, images, audio &amp; video — up to 30 MB.</p>
                </div>
            </header>
            @if($uploadableCourses->isEmpty())
                <div class="p-5">
                    <p class="text-sm text-slate-600">No courses are assigned to your class yet. Ask an admin to assign courses first.</p>
                </div>
            @else
                <form method="POST" action="{{ route('dashboard.materials.store') }}" enctype="multipart/form-data" class="p-5 grid grid-cols-1 sm:grid-cols-12 gap-4">
                    @csrf

                    @if($classes->count() > 1)
                        <div class="sm:col-span-4">
                            <label for="material_class_id" class="block text-xs font-semibold text-slate-700 mb-1">Class</label>
                            <select name="class_id" id="material_class_id" required class="w-full border-2 border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                @foreach($classes as $cls)
                                    <option value="{{ $cls->id }}" @selected(old('class_id') == $cls->id)>{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-8">
                    @else
                        <input type="hidden" name="class_id" value="{{ $classes->first()->id ?? '' }}">
                        <div class="sm:col-span-12">
                    @endif
                            <label for="material_course_id" class="block text-xs font-semibold text-slate-700 mb-1">Course</label>
                            <select name="course_id" id="material_course_id" required class="w-full border-2 border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                                <option value="">Select course…</option>
                                @foreach($uploadableCourses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                        {{ $course->course_name }}@if($course->course_code) ({{ $course->course_code }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    <div class="sm:col-span-12">
                        <label for="material_title" class="block text-xs font-semibold text-slate-700 mb-1">Title</label>
                        <input type="text" name="title" id="material_title" maxlength="200" required value="{{ old('title') }}" placeholder="e.g. Course outline — Week 1"
                               class="w-full border-2 border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">
                    </div>

                    <div class="sm:col-span-12">
                        <label for="material_description" class="block text-xs font-semibold text-slate-700 mb-1">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                        <textarea name="description" id="material_description" rows="2" maxlength="2000" placeholder="Notes or instructions for your classmates…"
                                  class="w-full border-2 border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary">{{ old('description') }}</textarea>
                    </div>

                    <div class="sm:col-span-12">
                        <label for="material_file" class="block text-xs font-semibold text-slate-700 mb-1">File</label>
                        <input type="file" name="file" id="material_file" required
                               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.zip,.rar,.7z,.jpg,.jpeg,.png,.gif,.webp,.mp3,.mp4,.m4a,.m4v,.mov"
                               class="block w-full text-sm text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-primary file:text-white file:font-semibold hover:file:bg-primary/90">
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
        <section class="rounded-xl border border-dashed border-slate-200 bg-white/50 px-6 py-12 text-center">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                <i class="fas fa-folder-open text-lg"></i>
            </span>
            <p class="text-sm font-semibold text-slate-700">No materials shared yet</p>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                @if($isRep)
                    Upload a course outline or lecture notes above to share with your class.
                @else
                    Once your class rep uploads outlines or lecture notes, you&rsquo;ll see them here.
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
            <section class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <header class="px-5 py-3 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 truncate">{{ $courseName }}@if($courseCode) <span class="text-slate-500 font-medium">({{ $courseCode }})</span>@endif</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ $items->count() }} file{{ $items->count() === 1 ? '' : 's' }}</p>
                    </div>
                    <span class="shrink-0 h-8 w-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <i class="fas fa-book text-xs"></i>
                    </span>
                </header>
                <ul class="divide-y divide-slate-100">
                    @foreach($items as $material)
                        @php
                            [$iconName, $iconCls] = $iconFor($material->fileKind());
                            $uploaderName = $material->uploader
                                ? trim((string) (($material->uploader->first_name ?? '').' '.($material->uploader->last_name ?? '')))
                                : '';
                        @endphp
                        <li class="px-5 py-3 flex items-start gap-3 hover:bg-slate-50/60 transition">
                            <span class="shrink-0 h-10 w-10 rounded-lg {{ $iconCls }} flex items-center justify-center">
                                <i class="fas {{ $iconName }} text-base"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $material->title }}</p>
                                @if($material->description)
                                    <p class="text-xs text-slate-600 mt-0.5 line-clamp-2">{{ $material->description }}</p>
                                @endif
                                <p class="text-[11px] text-slate-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-database text-slate-400 text-[9px]"></i>
                                        {{ $material->formattedFileSize() }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-clock text-slate-400 text-[9px]"></i>
                                        {{ $material->created_at?->diffForHumans() ?? '—' }}
                                    </span>
                                    @if($uploaderName !== '')
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fas fa-user text-slate-400 text-[9px]"></i>
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
                                @if($isRep)
                                    <form method="POST" action="{{ route('dashboard.materials.destroy', $material) }}"
                                          onsubmit="return confirm('Remove this material? Students will lose access.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white text-red-700 px-2.5 py-1.5 text-xs font-semibold hover:bg-red-50">
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
@endsection
