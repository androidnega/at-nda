<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DeletedStudentIndex;
use App\Models\Student;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $indexNumber = $request->input('index_number') ?? $request->query('index_number');
        $indexNumber = is_string($indexNumber) ? trim($indexNumber) : '';
        if (empty($indexNumber)) {
            return response()->json([
                'found' => false,
                'student' => null,
                'message' => 'Index number required',
                'error_code' => 'validation_error',
                'in_system' => false,
            ], 400);
        }
        $student = Student::findByIndex($indexNumber);
        if (! $student) {
            $normalized = strtoupper(trim($indexNumber));
            $removed = DeletedStudentIndex::query()
                ->where('index_number', $normalized)
                ->orderByDesc('deleted_at')
                ->first();

            $payload = [
                'found' => false,
                'student' => null,
                'in_system' => false,
                'was_removed' => $removed !== null,
                'message' => $removed
                    ? 'This index is no longer in the system'
                    : 'Index number not found',
                'error_code' => $removed ? 'student_removed' : 'student_not_found',
            ];
            if ($removed) {
                $payload['removed_at'] = $removed->deleted_at?->toIso8601String();
            }
            if (config('app.debug')) {
                $payload['debug'] = [
                    'received' => $indexNumber,
                    'length' => strlen($indexNumber),
                    'hint' => 'Check: index in DB? Format match? Try BC/ITD/24/001 or BCITD24001',
                ];
            }

            return response()->json($payload, 404);
        }
        $settings = SystemSetting::get();
        $class = $student->schoolClass;
        $dept = $student->department ?? $class?->department;
        $faculty = $dept?->faculty ?? $class?->faculty;
        $photoUrl = $student->profileImageUrl();
        $item = [
            'index_number' => $student->index_number,
            'name' => $student->getDisplayName(),
            'profile_image' => $photoUrl,
            'profile_image_url' => $photoUrl,
            'class' => $class?->name ?? null,
            'faculty' => $faculty?->name ?? null,
            'department' => $dept?->name ?? null,
            'level' => $class?->level ?? null,
            'phone' => $student->phone_number,
            'has_password' => !empty($student->password),
            'weekly_timetable' => $student->weeklyTimetableSummary(),
        ];
        if ($settings->enable_ip_binding && $student->bound_ip) {
            $item['bound_ip'] = $student->bound_ip;
        }
        return response()->json([
            'found' => true,
            'student' => $item,
            'in_system' => true,
        ]);
    }

    /**
     * GET /api/students/removed — index numbers deleted since `since` (for Flutter cache / “no longer in system”).
     *
     * Query: since (optional ISO8601) — only rows with deleted_at > since
     */
    public function removed(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $query = DeletedStudentIndex::query()->orderBy('deleted_at')->orderBy('id');

        if (is_string($since) && trim($since) !== '') {
            try {
                $boundary = Carbon::parse($since);
                $query->where('deleted_at', '>', $boundary);
            } catch (\Throwable) {
                return response()->json([
                    'message' => 'Invalid since parameter (use ISO8601)',
                    'error_code' => 'invalid_since',
                ], 422);
            }
        }

        $rows = $query->get();

        return response()->json([
            'removed' => $rows->map(fn (DeletedStudentIndex $r) => [
                'index_number' => $r->index_number,
                'deleted_at' => $r->deleted_at?->toIso8601String(),
            ])->values()->all(),
            'removed_indexes' => $rows->pluck('index_number')->values()->all(),
            'count' => $rows->count(),
        ]);
    }

    /**
     * GET /api/students/status?index_number=… — exists in DB vs removed log (Flutter quick check).
     */
    public function status(Request $request): JsonResponse
    {
        $raw = $request->query('index_number');
        $indexNumber = is_string($raw) ? strtoupper(trim($raw)) : '';
        if ($indexNumber === '') {
            return response()->json([
                'exists' => false,
                'in_system' => false,
                'was_removed' => false,
                'message' => 'index_number required',
                'error_code' => 'validation_error',
            ], 422);
        }

        $student = Student::findByIndex($indexNumber);
        if ($student) {
            return response()->json([
                'exists' => true,
                'in_system' => true,
                'was_removed' => false,
                'index_number' => $student->index_number,
            ]);
        }

        $removed = DeletedStudentIndex::query()
            ->where('index_number', $indexNumber)
            ->orderByDesc('deleted_at')
            ->first();

        if ($removed) {
            return response()->json([
                'exists' => false,
                'in_system' => false,
                'was_removed' => true,
                'removed_at' => $removed->deleted_at?->toIso8601String(),
                'index_number' => $removed->index_number,
                'message' => 'This index is no longer in the system',
                'error_code' => 'student_removed',
            ]);
        }

        return response()->json([
            'exists' => false,
            'in_system' => false,
            'was_removed' => false,
            'message' => 'Index not found',
            'error_code' => 'student_not_found',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $indexNumber = $request->query('index_number');
        if ($indexNumber !== null && $indexNumber !== '') {
            $indexNumber = strtoupper(trim((string) $indexNumber));
            $student = Student::findByIndex($indexNumber);
            if (!$student) {
                return response()->json([]);
            }
            $student->load(['schoolClass.faculty', 'schoolClass.department', 'department', 'department.faculty']);
            $students = collect([$student]);
        } else {
            $query = Student::query();
            if ($classId = $request->query('class_id')) {
                $query->where('class_id', $classId);
            }
            if ($courseId = $request->query('course_id')) {
                $course = Course::find($courseId);
                if ($course?->class_id) {
                    $query->where('class_id', $course->class_id);
                }
            }
            // Avoid loading entire table on mobile (slow / huge JSON). Override with ?limit= up to 2000.
            $limit = (int) $request->query('limit', 500);
            $limit = max(1, min($limit, 2000));
            $students = $query->with(['schoolClass.faculty', 'schoolClass.department', 'department', 'department.faculty'])
                ->orderByRaw('COALESCE(last_name, index_number)')->orderByRaw('COALESCE(first_name, index_number)')
                ->limit($limit)
                ->get();
        }
        $settings = SystemSetting::get();
        $includeBoundIp = $settings->enable_ip_binding;

        $data = $students->map(function (Student $student) use ($includeBoundIp) {
            $class = $student->schoolClass;
            $dept = $student->department ?? $class?->department;
            $faculty = $dept?->faculty ?? $class?->faculty;

            $photoUrl = $student->profileImageUrl();
            $item = [
                'id' => $student->id,
                'index_number' => $student->index_number,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'name' => $student->getDisplayName(),
                'class_name' => $class?->name ?? null,
                'class' => $class?->name ?? null,
                'faculty' => $faculty?->name ?? null,
                'department' => $dept?->name ?? null,
                'level' => $class?->level ?? null,
                'phone_number' => $student->phone_number,
                'phone' => $student->phone_number,
                'profile_image' => $photoUrl,
                'profile_image_url' => $photoUrl,
                'has_password' => !empty($student->password),
            ];


            if ($includeBoundIp && $student->bound_ip) {
                $item['bound_ip'] = $student->bound_ip;
            }

            return $item;
        });

        return response()->json($data);
    }
}
