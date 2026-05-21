<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Lecturer;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\RepCourseAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manages course-outline / lecture materials uploaded by either a class rep
 * (scoped to their class+course) or a lecturer (scoped to the classes they
 * teach the course for). Students in those classes can download them from
 * their own dashboard; non-uploaders can never modify other uploaders' files.
 */
class CourseMaterialController extends Controller
{
    private const STORAGE_DIR = 'course-materials';

    /**
     * Maximum upload size in kilobytes (Laravel's validation expects KB).
     * 30 MB caps things like big lecture-slide bundles without letting users
     * fill the disk with arbitrary files.
     */
    private const MAX_KB = 30 * 1024;

    public function index(Request $request): View|RedirectResponse
    {
        $lecturer = $this->resolveLecturer($request);
        if ($lecturer instanceof Lecturer) {
            return $this->lecturerIndex($request, $lecturer);
        }

        $student = $this->requireStudent($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $isRep = $student->isClassRep();
        // Reps see materials for any class they manage; regular students see
        // only their own class's materials.
        $classIds = $isRep
            ? $student->repManagedClassIds()->map(fn ($id) => (int) $id)->all()
            : array_filter([(int) ($student->class_id ?? 0)]);

        if ($classIds === []) {
            return view('materials.index', [
                'student' => $student,
                'isRep' => $isRep,
                'isLecturer' => false,
                'materialsByCourse' => collect(),
                'uploadableCourses' => collect(),
                'classes' => collect(),
                'courseClassMap' => collect(),
                'dashboardRole' => $isRep ? 'classrep' : 'student',
            ]);
        }

        $materials = CourseMaterial::query()
            ->whereIn('class_id', $classIds)
            ->with(['course', 'schoolClass', 'uploader', 'lecturerUploader'])
            ->orderBy('course_id')
            ->orderByDesc('created_at')
            ->get();

        $materialsByCourse = $materials->groupBy('course_id');

        // Reps need a list of courses they're allowed to upload to.
        $uploadableCourses = $isRep
            ? RepCourseAccess::coursesQueryForRep($student)
                ->orderBy('course_name')
                ->get(['id', 'course_name', 'course_code'])
            : collect();

        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('materials.index', [
            'student' => $student,
            'isRep' => $isRep,
            'isLecturer' => false,
            'materialsByCourse' => $materialsByCourse,
            'uploadableCourses' => $uploadableCourses,
            'classes' => $classes,
            'courseClassMap' => collect(),
            'dashboardRole' => $isRep ? 'classrep' : 'student',
        ]);
    }

    /**
     * Lecturer-side listing: every class the lecturer teaches the course for,
     * plus an upload widget that's pre-scoped to (course, class) tuples they
     * actually own.
     */
    private function lecturerIndex(Request $request, Lecturer $lecturer): View
    {
        $courses = $lecturer->teachingCourses();
        // For each course collect the classes that course is assigned to
        // and that the lecturer is allowed to upload for.
        $allowedClassIds = $lecturer->assignedClassIds()->map(fn ($id) => (int) $id)->all();

        $courseClassMap = collect();
        foreach ($courses as $course) {
            $courseClassIds = collect($course->assignedClassIds())
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $allowedClassIds === [] || in_array($id, $allowedClassIds, true))
                ->values();
            if ($courseClassIds->isNotEmpty()) {
                $courseClassMap->put((int) $course->id, $courseClassIds->all());
            }
        }

        $classIdsFlat = $courseClassMap->flatten()->unique()->map(fn ($id) => (int) $id)->all();
        $classes = $classIdsFlat === []
            ? collect()
            : SchoolClass::query()->whereIn('id', $classIdsFlat)->orderBy('name')->get(['id', 'name']);

        $materials = $classIdsFlat === [] || $courses->isEmpty()
            ? collect()
            : CourseMaterial::query()
                ->whereIn('class_id', $classIdsFlat)
                ->whereIn('course_id', $courses->pluck('id')->all())
                ->with(['course', 'schoolClass', 'uploader', 'lecturerUploader'])
                ->orderBy('course_id')
                ->orderByDesc('created_at')
                ->get();
        $materialsByCourse = $materials instanceof \Illuminate\Support\Collection
            ? $materials->groupBy('course_id')
            : collect();

        return view('materials.index', [
            'student' => null,
            'lecturer' => $lecturer,
            'isRep' => false,
            'isLecturer' => true,
            'materialsByCourse' => $materialsByCourse,
            'uploadableCourses' => $courses,
            'classes' => $classes,
            'courseClassMap' => $courseClassMap,
            'dashboardRole' => 'admin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lecturer = $this->resolveLecturer($request);
        if ($lecturer instanceof Lecturer) {
            return $this->lecturerStore($request, $lecturer);
        }

        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $validated = $request->validate([
            'class_id' => 'required|integer|exists:classes,id',
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'file' => $this->fileValidationRules(),
        ]);

        $classId = (int) $validated['class_id'];
        if (! $student->repManagedClassIds()->map(fn ($id) => (int) $id)->contains($classId)) {
            abort(403, 'You may only upload materials for classes you rep.');
        }

        $course = Course::findOrFail($validated['course_id']);
        $assignedClassIds = collect($course->assignedClassIds())->map(fn ($id) => (int) $id);
        if (! $assignedClassIds->contains($classId)) {
            return back()->with('error', 'This course is not assigned to the selected class.');
        }

        $this->persistMaterial($request, $validated, $course, $classId, [
            'uploaded_by_student_id' => $student->id,
        ]);

        return redirect()
            ->route('dashboard.materials.index')
            ->with('success', 'Material uploaded and shared with '.($course->course_name ?? 'the course').'.');
    }

    private function lecturerStore(Request $request, Lecturer $lecturer): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:classes,id',
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'file' => $this->fileValidationRules(),
        ]);

        $classId = (int) $validated['class_id'];
        $course = Course::findOrFail($validated['course_id']);

        if (! $lecturer->managesCourse($course)) {
            abort(403, 'You may only upload materials for courses assigned to you.');
        }

        $assignedClassIds = collect($course->assignedClassIds())->map(fn ($id) => (int) $id);
        if (! $assignedClassIds->contains($classId)) {
            return back()->with('error', 'This course is not assigned to the selected class.');
        }

        $lecturerClassIds = $lecturer->assignedClassIds()->map(fn ($id) => (int) $id);
        if ($lecturerClassIds->isNotEmpty() && ! $lecturerClassIds->contains($classId)) {
            return back()->with('error', 'You can only share material with classes you teach.');
        }

        $this->persistMaterial($request, $validated, $course, $classId, [
            'uploaded_by_lecturer_id' => $lecturer->id,
        ]);

        return redirect()
            ->route('dashboard.materials.index')
            ->with('success', 'Material uploaded and shared with '.($course->course_name ?? 'the course').'.');
    }

    public function destroy(Request $request, CourseMaterial $material): RedirectResponse
    {
        $lecturer = $this->resolveLecturer($request);
        if ($lecturer instanceof Lecturer) {
            // A lecturer can remove anything they uploaded, or anything tied
            // to a course they currently teach (so an outdated rep upload
            // can be cleared by the lecturer who actually owns the course).
            $ownsUpload = (int) $material->uploaded_by_lecturer_id === (int) $lecturer->id;
            $course = Course::find($material->course_id);
            $ownsCourse = $course ? $lecturer->managesCourse($course) : false;
            if (! $ownsUpload && ! $ownsCourse) {
                abort(403, 'You may only delete materials you uploaded or for your own courses.');
            }
            $this->deleteFileAndRow($material);

            return redirect()
                ->route('dashboard.materials.index')
                ->with('success', 'Material removed.');
        }

        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $managed = $student->repManagedClassIds()->map(fn ($id) => (int) $id);
        if (! $managed->contains((int) $material->class_id)) {
            abort(403, 'You may only delete materials for classes you rep.');
        }
        // A rep can clear their own upload or a lecturer's upload only if
        // they manage that class — they shouldn't be able to nuke a
        // colleague rep's upload from another class but the class scope
        // already guards that.

        $this->deleteFileAndRow($material);

        return redirect()
            ->route('dashboard.materials.index')
            ->with('success', 'Material removed.');
    }

    public function download(Request $request, CourseMaterial $material): StreamedResponse
    {
        $lecturer = $this->resolveLecturer($request);
        if ($lecturer instanceof Lecturer) {
            $course = Course::find($material->course_id);
            $ownsCourse = $course ? $lecturer->managesCourse($course) : false;
            $assignedClasses = $lecturer->assignedClassIds()->map(fn ($id) => (int) $id);
            $assigned = $assignedClasses->contains((int) $material->class_id);
            if (! $ownsCourse && ! $assigned) {
                abort(403, 'You do not teach this class for this course.');
            }

            return $this->streamFile($material);
        }

        $student = $this->requireStudent($request);
        if ($student instanceof RedirectResponse) {
            abort(403);
        }

        $studentClassId = (int) ($student->class_id ?? 0);
        $repClassIds = $student->repManagedClassIds()->map(fn ($id) => (int) $id);
        $materialClassId = (int) $material->class_id;
        $allowed = $studentClassId === $materialClassId || $repClassIds->contains($materialClassId);
        if (! $allowed) {
            abort(403, 'This material is shared with a different class.');
        }

        return $this->streamFile($material);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $uploaderColumns  e.g. ['uploaded_by_student_id' => 4]
     */
    private function persistMaterial(Request $request, array $validated, Course $course, int $classId, array $uploaderColumns): void
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storedName = Str::ulid().'.'.$extension;
        $relativeDir = self::STORAGE_DIR.'/'.date('Y').'/'.date('m');
        $relativePath = $file->storeAs($relativeDir, $storedName, 'local');

        CourseMaterial::create(array_merge([
            'course_id' => $course->id,
            'class_id' => $classId,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'file_path' => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize() ?: null,
        ], $uploaderColumns));
    }

    private function deleteFileAndRow(CourseMaterial $material): void
    {
        if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();
    }

    private function streamFile(CourseMaterial $material): StreamedResponse
    {
        if (! $material->file_path || ! Storage::disk('local')->exists($material->file_path)) {
            abort(404, 'The file is no longer available.');
        }

        $downloadName = $material->original_name ?: ($material->title.'.'.pathinfo($material->file_path, PATHINFO_EXTENSION));

        return Storage::disk('local')->download(
            $material->file_path,
            $downloadName,
            ['Content-Type' => $material->mime_type ?: 'application/octet-stream']
        );
    }

    /**
     * @return array<int, string>
     */
    private function fileValidationRules(): array
    {
        return [
            'required',
            'file',
            'max:'.self::MAX_KB,
            // Common course-outline / lecture-material formats.
            'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,zip,rar,7z,jpg,jpeg,png,gif,webp,mp3,mp4,m4a,m4v,mov',
        ];
    }

    private function resolveLecturer(Request $request): ?Lecturer
    {
        if ($request->session()->has('admin_id')) {
            return null;
        }
        $lecturerId = $request->session()->get('lecturer_id');
        if (! $lecturerId) {
            return null;
        }

        return Lecturer::find($lecturerId);
    }

    private function requireStudent(Request $request): Student|RedirectResponse
    {
        $studentId = $request->session()->get('student_id');
        $student = $studentId ? Student::find($studentId) : null;
        if (! $student) {
            return redirect()->route('home')->with('info', 'Please sign in to continue.');
        }

        return $student;
    }

    private function requireRep(Request $request): Student|RedirectResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }
        if (! $student->isClassRep()) {
            return redirect()
                ->route('dashboard.materials.index')
                ->with('error', 'Only class reps can upload or delete materials.');
        }

        return $student;
    }
}
