<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Course;
use App\Models\DeletedStudentIndex;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class StudentController extends Controller
{
    /**
     * Resolve the authenticated principal (Student or Lecturer) for the
     * incoming request. Prefers the value stashed by
     * EnsureCallerCanSeeClass; falls back to a fresh Sanctum lookup so
     * the controller still works if the middleware is not (yet)
     * attached to a given route. Returns null when no valid bearer
     * is present.
     */
    private function principalFromRequest(Request $request): ?Model
    {
        $principal = $request->attributes->get('caller_principal');
        if ($principal instanceof Model) {
            return $principal;
        }

        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return null;
        }
        $pat = PersonalAccessToken::findToken($bearer);
        if ($pat === null) {
            return null;
        }
        $owner = $pat->tokenable;

        return $owner instanceof Model ? $owner : null;
    }

    /**
     * Class id the controller should default to when the caller did
     * not pass one explicitly. Students default to their own class;
     * lecturers default to the first class they are assigned to (via
     * the class_lecturer pivot when present, otherwise the legacy
     * class_id column).
     */
    private function defaultClassIdFor(Model $principal): ?int
    {
        if ($principal instanceof Student) {
            return $principal->class_id ? (int) $principal->class_id : null;
        }
        if ($principal instanceof Lecturer) {
            if (\App\Support\SchemaFeatures::hasClassLecturerPivot()) {
                return (int) ($principal->schoolClasses()->value('classes.id') ?? 0) ?: null;
            }

            return $principal->class_id ? (int) $principal->class_id : null;
        }

        return null;
    }

    /**
     * True when the principal is entitled to see the target student.
     * Mirrors EnsureCallerCanSeeClass::isAllowed but operates on a
     * Student row rather than a raw class id, so that single-record
     * lookups (`?index_number=...`) are scoped the same way as
     * multi-row listings.
     */
    private function principalCanSeeStudent(Model $principal, Student $target): bool
    {
        if ($principal instanceof Student) {
            if ((int) ($principal->class_id ?? 0) === (int) ($target->class_id ?? 0)
                && (int) ($principal->class_id ?? 0) > 0) {
                return true;
            }
            $managed = $principal->repManagedClassIds()->map(fn ($id) => (int) $id)->all();

            return in_array((int) ($target->class_id ?? 0), $managed, true);
        }
        if ($principal instanceof Lecturer) {
            if (\App\Support\SchemaFeatures::hasClassLecturerPivot()) {
                return $principal->schoolClasses()
                    ->where('classes.id', (int) ($target->class_id ?? 0))
                    ->exists();
            }

            return (int) ($principal->class_id ?? 0) === (int) ($target->class_id ?? 0);
        }

        return false;
    }

    /**
     * True when the principal is a lecturer or a class rep — i.e.
     * staff-like enough to receive student PII (phone, IP binding)
     * in roster responses. Regular students get the PII-stripped
     * payload.
     */
    private function principalIsStaffLike(Model $principal): bool
    {
        if ($principal instanceof Lecturer) {
            return true;
        }
        if ($principal instanceof Student) {
            return $principal->isClassRep();
        }

        return false;
    }

    public function index(Request $request): JsonResponse
    {
        $principal = $this->principalFromRequest($request);
        if ($principal === null) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $indexNumber = $request->query('index_number');
        if ($indexNumber !== null && $indexNumber !== '') {
            $indexNumber = strtoupper(trim((string) $indexNumber));
            $student = Student::findByIndex($indexNumber);
            if (! $student) {
                return response()->json([]);
            }
            // Single-row response must still respect class scope.
            if (! $this->principalCanSeeStudent($principal, $student)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $student->load(['schoolClass.faculty', 'schoolClass.department', 'department', 'department.faculty']);
            $students = collect([$student]);
        } else {
            // Multi-row list: class_id must come from the principal or be
            // a class the principal is entitled to see (already enforced
            // by EnsureCallerCanSeeClass when class_id is passed).
            $classId = (int) ($request->query('class_id')
                ?? $this->defaultClassIdFor($principal)
                ?? 0);
            if ($classId <= 0) {
                return response()->json([]);
            }

            $query = Student::query()->where('class_id', $classId);

            if ($courseId = $request->query('course_id')) {
                $course = Course::find($courseId);
                if ($course === null || ! $course->isAssignedToClass($classId)) {
                    return response()->json([]);
                }
            }

            // Default limit 100. Hard cap 500. Higher previously was 2000
            // — never required for legitimate use.
            $limit = (int) $request->query('limit', 100);
            $limit = max(1, min($limit, 500));
            $students = $query->with(['schoolClass.faculty', 'schoolClass.department', 'department', 'department.faculty'])
                ->orderByRaw('COALESCE(last_name, index_number)')
                ->orderByRaw('COALESCE(first_name, index_number)')
                ->limit($limit)
                ->get();
        }

        $settings = SystemSetting::get();
        $includePhone = $this->principalIsStaffLike($principal);
        $includeBoundIp = $includePhone && (bool) $settings->enable_ip_binding;

        $data = $students->map(function (Student $student) use ($includePhone, $includeBoundIp) {
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
                'profile_image' => $photoUrl,
                'profile_image_url' => $photoUrl,
                'has_password' => ! empty($student->password),
            ];

            if ($includePhone) {
                $item['phone_number'] = $student->phone_number;
                $item['phone'] = $student->phone_number;
            }

            if ($includeBoundIp && $student->bound_ip) {
                $item['bound_ip'] = $student->bound_ip;
            }

            return $item;
        });

        return response()->json($data);
    }

    public function lookup(Request $request): JsonResponse
    {
        $principal = $this->principalFromRequest($request);
        if ($principal === null) {
            return response()->json([
                'found' => false,
                'student' => null,
                'message' => 'Authentication required',
                'error_code' => 'unauthenticated',
            ], 401);
        }

        $indexNumber = $request->input('index_number') ?? $request->query('index_number');
        $indexNumber = is_string($indexNumber) ? trim($indexNumber) : '';
        if ($indexNumber === '') {
            return response()->json([
                'found' => false,
                'student' => null,
                'message' => 'Index number required',
                'error_code' => 'validation_error',
            ], 400);
        }

        $student = Student::findByIndex($indexNumber);
        if ($student === null) {
            $removed = DeletedStudentIndex::latestForIndex(strtoupper($indexNumber));

            return response()->json([
                'found' => false,
                'student' => null,
                'in_system' => false,
                'was_removed' => $removed !== null,
                'message' => $removed
                    ? 'This index is no longer in the system'
                    : 'Index number not found',
                'error_code' => $removed ? 'student_removed' : 'student_not_found',
            ], 404);
        }

        if (! $this->principalCanSeeStudent($principal, $student)) {
            return response()->json([
                'found' => false,
                'student' => null,
                'message' => 'Forbidden',
                'error_code' => 'forbidden',
            ], 403);
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
            'has_password' => ! empty($student->password),
        ];

        if ($this->principalIsStaffLike($principal)) {
            $item['phone'] = $student->phone_number;
            $item['weekly_timetable'] = $student->weeklyTimetableSummary();
            if ($settings->enable_ip_binding && $student->bound_ip) {
                $item['bound_ip'] = $student->bound_ip;
            }
        }

        return response()->json([
            'found' => true,
            'student' => $item,
            'in_system' => true,
        ]);
    }

    /**
     * POST /api/students/quick-status  (rate-limited, no auth)
     *
     * Minimal lookup for the web sign-in funnel: returns ONLY whether
     * the index exists, whether a password is set, and an initials
     * display string. NEVER returns PII.
     */
    public function quickStatus(Request $request): JsonResponse
    {
        $raw = $request->input('index_number') ?? $request->query('index_number');
        $indexNumber = is_string($raw) ? strtoupper(trim($raw)) : '';
        if ($indexNumber === '') {
            return response()->json([
                'found' => false,
                'message' => 'index_number required',
            ], 422);
        }

        $student = Student::findByIndex($indexNumber);
        if ($student === null) {
            // Anti-enumeration: same payload for not-found and removed.
            return response()->json([
                'found' => false,
                'has_password' => false,
                'display_initials' => '—',
            ]);
        }

        return response()->json([
            'found' => true,
            'has_password' => ! empty($student->password),
            'display_initials' => $student->avatarInitials(),
        ]);
    }

    /**
     * GET /api/students/removed — index numbers deleted since `since`.
     *
     * Requires Sanctum. Full list is restricted to staff-like
     * principals (lecturers + class reps); regular students should
     * use `status` to check a single index instead.
     *
     * Query: since (optional ISO8601) — only rows with deleted_at > since
     */
    public function removed(Request $request): JsonResponse
    {
        if (! DeletedStudentIndex::tableReady()) {
            return response()->json([
                'removed' => [],
                'removed_indexes' => [],
                'count' => 0,
            ]);
        }

        $principal = $this->principalFromRequest($request);
        if ($principal === null) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only admins (or per-class staff) get the full list. Students
        // who want to know if their OWN index was removed use `status`.
        $isAdminLike = $principal instanceof Lecturer
            || ($principal instanceof Student && $principal->isClassRep());

        if (! $isAdminLike) {
            return response()->json([
                'message' => 'Use /api/students/status?index_number=... to check a single index.',
            ], 403);
        }

        $since = $request->query('since');
        $query = DeletedStudentIndex::query()->orderBy('deleted_at')->orderBy('id');

        if (is_string($since) && trim($since) !== '') {
            try {
                $query->where('deleted_at', '>', Carbon::parse($since));
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
     * GET /api/students/status?index_number=… — exists in DB vs removed log.
     *
     * Requires Sanctum. Per-index check available to any authenticated
     * principal (so a student can self-verify their own index after
     * the admin tooling deletes them).
     */
    public function status(Request $request): JsonResponse
    {
        if ($this->principalFromRequest($request) === null) {
            return response()->json([
                'exists' => false,
                'in_system' => false,
                'was_removed' => false,
                'message' => 'Authentication required',
                'error_code' => 'unauthenticated',
            ], 401);
        }

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

        $removed = DeletedStudentIndex::latestForIndex($indexNumber);

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

    /**
     * GET /api/v1/students — Sanctum only; list students in the same class as the token holder.
     */
    public function indexV1Authenticated(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Student) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication required',
                'data' => [],
            ], 401);
        }

        if ($user->class_id === null) {
            return response()->json([
                'status' => true,
                'message' => 'No class assigned',
                'data' => StudentResource::collection(collect()),
            ]);
        }

        $students = Student::query()
            ->where('class_id', $user->class_id)
            ->with(['schoolClass.faculty', 'schoolClass.department', 'department', 'department.faculty'])
            ->orderByRaw('COALESCE(last_name, index_number)')
            ->orderByRaw('COALESCE(first_name, index_number)')
            ->limit(2000)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Students fetched successfully',
            'data' => StudentResource::collection($students),
        ]);
    }
}
