<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Student;
use App\Support\RepCourseAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manages course-outline / lecture materials that a class rep uploads for a
 * specific class+course combination. Students in that class can download
 * them from their own dashboard; non-reps can never upload or delete.
 */
class CourseMaterialController extends Controller
{
    private const STORAGE_DIR = 'course-materials';

    /**
     * Maximum upload size in kilobytes (Laravel's validation expects KB).
     * 30 MB caps things like big lecture-slide bundles without letting reps
     * fill the disk with arbitrary files.
     */
    private const MAX_KB = 30 * 1024;

    public function index(Request $request): View|RedirectResponse
    {
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
                'materialsByCourse' => collect(),
                'uploadableCourses' => collect(),
                'classes' => collect(),
                'dashboardRole' => $isRep ? 'classrep' : 'student',
            ]);
        }

        $materials = CourseMaterial::query()
            ->whereIn('class_id', $classIds)
            ->with(['course', 'schoolClass', 'uploader'])
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

        $classes = \App\Models\SchoolClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('materials.index', [
            'student' => $student,
            'isRep' => $isRep,
            'materialsByCourse' => $materialsByCourse,
            'uploadableCourses' => $uploadableCourses,
            'classes' => $classes,
            'dashboardRole' => $isRep ? 'classrep' : 'student',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $validated = $request->validate([
            'class_id' => 'required|integer|exists:classes,id',
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KB,
                // Common course-outline / lecture-material formats.
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,zip,rar,7z,jpg,jpeg,png,gif,webp,mp3,mp4,m4a,m4v,mov',
            ],
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

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storedName = Str::ulid().'.'.$extension;
        $relativeDir = self::STORAGE_DIR.'/'.date('Y').'/'.date('m');
        $relativePath = $file->storeAs($relativeDir, $storedName, 'local');

        CourseMaterial::create([
            'course_id' => $course->id,
            'class_id' => $classId,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'file_path' => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize() ?: null,
            'uploaded_by_student_id' => $student->id,
        ]);

        return redirect()
            ->route('dashboard.materials.index')
            ->with('success', 'Material uploaded and shared with '.($course->course_name ?? 'the course').'.');
    }

    public function destroy(Request $request, CourseMaterial $material): RedirectResponse
    {
        $student = $this->requireRep($request);
        if ($student instanceof RedirectResponse) {
            return $student;
        }

        $managed = $student->repManagedClassIds()->map(fn ($id) => (int) $id);
        if (! $managed->contains((int) $material->class_id)) {
            abort(403, 'You may only delete materials for classes you rep.');
        }

        if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();

        return redirect()
            ->route('dashboard.materials.index')
            ->with('success', 'Material removed.');
    }

    public function download(Request $request, CourseMaterial $material): StreamedResponse
    {
        $student = $this->requireStudent($request);
        if ($student instanceof RedirectResponse) {
            abort(403);
        }

        // Student must be in the material's class (or a rep who manages it).
        $studentClassId = (int) ($student->class_id ?? 0);
        $repClassIds = $student->repManagedClassIds()->map(fn ($id) => (int) $id);
        $materialClassId = (int) $material->class_id;
        $allowed = $studentClassId === $materialClassId || $repClassIds->contains($materialClassId);
        if (! $allowed) {
            abort(403, 'This material is shared with a different class.');
        }

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
