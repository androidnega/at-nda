<?php

namespace App\Http\Controllers;

use App\Imports\StudentsImport;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Student::with(['schoolClass', 'courseReps.course']);

        $search = $request->get('search');
        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('index_number', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }

        $classId = $request->get('class_id');
        if ($classId) {
            $query->where('class_id', $classId);
        }

        $program = $request->get('program');
        if ($program) {
            $query->where('index_number', 'like', strtoupper($program) . '%');
        }

        $students = $query->with(['classReps.schoolClass', 'courseReps'])->latest()->paginate(30)->withQueryString();
        $classes = \App\Models\SchoolClass::orderBy('name')->get();

        if ($request->wantsJson()) {
            $items = collect($students->items())->map(function ($s) {
                $idx = strtoupper($s->index_number ?? '');
                $programKey = str_contains($idx, 'ITN') ? 'ITN' : (str_contains($idx, 'ITS') ? 'ITS' : (str_contains($idx, 'ITD') ? 'ITD' : null));
                return [
                    'id' => $s->id,
                    'index_number' => $s->index_number,
                    'display_name' => $s->getDisplayNameOrIndex(),
                    'program_label' => $s->getProgramLabel(),
                    'program_key' => $programKey,
                    'class_name' => $s->schoolClass?->name,
                    'is_rep' => $s->isRep(),
                    'profile_image_url' => $s->profileImageUrl(),
                    'avatar_initials' => $s->avatarInitials(),
                    'class_reps' => $s->classReps->map(fn ($cr) => ['class' => $cr->schoolClass?->name, 'role' => $cr->role])->toArray(),
                ];
            });
            return response()->json([
                'students' => $items,
                'has_more' => $students->hasMorePages(),
                'next_page' => $students->currentPage() + 1,
            ]);
        }

        return view('admin.students', compact('students', 'classes'));
    }

    public function assignRep(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'role' => 'required|in:rep,assist',
        ]);
        $existing = \App\Models\ClassRep::where('student_id', $student->id)
            ->where('class_id', $validated['class_id'])
            ->first();
        if ($existing) {
            $existing->update(['role' => $validated['role']]);
        } else {
            \App\Models\ClassRep::create([
                'student_id' => $student->id,
                'class_id' => $validated['class_id'],
                'role' => $validated['role'],
            ]);
        }
        $roleLabel = $validated['role'] === 'rep' ? 'Class Rep' : 'Assistant Rep';
        return back()->with('success', $student->getDisplayNameOrIndex() . ' assigned as ' . $roleLabel);
    }

    public function removeRep(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate(['class_id' => 'required|exists:classes,id']);
        \App\Models\ClassRep::where('student_id', $student->id)
            ->where('class_id', $validated['class_id'])
            ->delete();
        return back()->with('success', 'Rep assignment removed');
    }

    public function show(Student $student): View
    {
        $student->load([
            'department.faculty',
            'schoolClass.faculty',
            'schoolClass.department',
            'schoolClass.semester',
            'classReps.schoolClass',
            'courseReps.course',
            'deviceToken',
        ]);
        $coursesCount = $student->schoolClass ? $student->schoolClass->courses()->count() : 0;
        $presentCount = $student->attendances()->count();
        $courseIds = $student->schoolClass ? $student->schoolClass->courses()->pluck('id')->toArray() : [];
        $totalWeeks = $courseIds ? \DB::table('attendance_weeks')->whereIn('course_id', $courseIds)->count() : 0;
        $absentCount = max(0, $totalWeeks - $presentCount);
        $classes = \App\Models\SchoolClass::orderBy('name')->get();
        $recentAttendance = $student->attendances()
            ->with(['course', 'attendanceWeek'])
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.student-detail', compact(
            'student',
            'coursesCount',
            'presentCount',
            'absentCount',
            'totalWeeks',
            'classes',
            'recentAttendance'
        ));
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->profile_image) {
            Storage::disk('public')->delete($student->profile_image);
        }
        $label = $student->getDisplayNameOrIndex();
        $student->delete();

        return redirect()->route('dashboard.students.index')->with('success', 'Student removed: '.$label);
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        $password = \Illuminate\Support\Str::password(12);
        $student->update(['password' => \Illuminate\Support\Facades\Hash::make($password)]);
        return back()->with('success', 'Password generated for ' . $student->index_number . '. New password: ' . $password);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        Excel::import(new StudentsImport, $request->file('file'));

        return redirect()->route('dashboard.students.index')->with('success', 'Students imported successfully.');
    }
}
