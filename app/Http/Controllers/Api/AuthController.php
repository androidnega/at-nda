<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeletedStudentIndex;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Support\PasswordPolicy;
use App\Support\PostMarkSessionLock;
use App\Support\StudentApiPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Student login (API).
     * POST /api/login
     * Body: { "index_number": "BC/ITD/24/047", "password": "123456" }
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
        ]);

        $rawLogin = trim($validated['index_number']);
        $indexNumber = strtoupper($rawLogin);
        $password = $validated['password'];

        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexNumber])->first();
        if (! $student) {
            $loginLower = strtolower(trim($rawLogin));
            // Staff: match email or username case-insensitively (nullable columns use COALESCE).
            $lecturer = Lecturer::query()
                ->where(function ($q) use ($loginLower) {
                    $q->whereRaw('LOWER(TRIM(COALESCE(email, \'\'))) = ?', [$loginLower])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(username, \'\'))) = ?', [$loginLower]);
                })
                ->first();

            if ($lecturer) {
                if (empty($lecturer->password)) {
                    return response()->json([
                        'message' => 'Mobile login is not enabled for this account. Ask your administrator to create a staff login under Staff accounts.',
                        'error_code' => 'lecturer_no_password',
                    ], 422);
                }
                if (! PasswordPolicy::matches($password, $lecturer->password)) {
                    return response()->json(['message' => 'Wrong password'], 401);
                }

                return response()->json($this->loginSuccessPayloadLecturer($lecturer, $rawLogin));
            }

            $removed = DeletedStudentIndex::latestForIndex($indexNumber);

            return response()->json([
                'message' => $removed
                    ? 'This account is no longer in the system'
                    : 'Index not found',
                'error_code' => $removed ? 'student_removed' : 'student_not_found',
                'in_system' => false,
                'was_removed' => $removed !== null,
                'removed_at' => $removed?->deleted_at?->toIso8601String(),
            ], 404);
        }

        if (empty($student->password)) {
            $settings = SystemSetting::get();
            if ($settings->require_password_on_first_login ?? true) {
                return response()->json([
                    'message' => 'Set password first via web',
                    'needs_set_password' => true,
                    'index_number' => $student->index_number,
                ], 422);
            }

            return response()->json($this->loginSuccessPayload($student));
        }

        if (! PasswordPolicy::matches($password, $student->password)) {
            return response()->json(['message' => 'Wrong password'], 401);
        }

        if ($lock = PostMarkSessionLock::activeLock($student)) {
            return response()->json([
                'message' => PostMarkSessionLock::blockMessage($lock),
                'error_code' => 'post_mark_session_lock',
            ], 403);
        }

        return response()->json($this->loginSuccessPayload($student));
    }

    /**
     * POST /api/me — same credentials as login; returns current profile (Flutter “fetch user”).
     */
    public function me(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    /**
     * Revoke the current Sanctum token (Bearer). POST /api/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return response()->json(['message' => 'No token provided'], 401);
        }

        $pat = PersonalAccessToken::findToken($bearer);
        if (! $pat) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        $pat->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    /**
     * Wraps profile for Flutter: { user: {...}, token, token_type } plus legacy top-level fields.
     * Sanctum personal access token (same ability name as /api/v1/auth/login).
     */
    private function loginSuccessPayload(Student $student): array
    {
        $user = StudentApiPayload::forUser($student);

        $student->tokens()->where('name', 'mobile')->delete();
        $token = $student->createToken('mobile', ['*'])->plainTextToken;

        return array_merge($user, [
            'user' => $user,
            'student' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Mobile lecturer login (same POST /api/login body; match email or username when no student index matches).
     *
     * @return array<string, mixed>
     */
    private function loginSuccessPayloadLecturer(Lecturer $lecturer, string $loginId): array
    {
        $lecturer->tokens()->where('name', 'mobile')->delete();
        $token = $lecturer->createToken('mobile', ['*'])->plainTextToken;
        $uid = trim((string) ($lecturer->email ?: $lecturer->username ?: $loginId));
        $user = [
            'id' => null,
            'name' => $lecturer->name,
            'student_id' => $uid,
            'index_number' => $uid,
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'lecturer_id' => (int) $lecturer->id,
            'primary_role' => 'lecturer',
            'is_class_rep' => false,
            'rep_roles' => [],
            'profile_image' => null,
            'profile_picture' => null,
            'profile_image_url' => null,
            'class_name' => null,
            'faculty' => null,
            'department' => null,
            'level' => null,
            'semester' => null,
            'phone_number' => null,
            'email' => $lecturer->email,
        ];

        return [
            'user' => $user,
            'student' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
