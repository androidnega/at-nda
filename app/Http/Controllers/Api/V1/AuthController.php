<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\DeletedStudentIndex;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Support\ApiEnvelope;
use App\Support\StudentApiPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Sanctum token auth for /api/v1/* (legacy /api/login unchanged).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $indexNumber = strtoupper(trim($validated['index_number']));
        $password = $validated['password'];

        $student = Student::whereRaw('UPPER(TRIM(index_number)) = ?', [$indexNumber])->first();
        if (! $student) {
            $removed = DeletedStudentIndex::query()
                ->where('index_number', $indexNumber)
                ->orderByDesc('deleted_at')
                ->first();
            Log::warning('api.v1.login.failed', [
                'reason' => $removed ? 'student_removed' : 'index_not_found',
                'index' => $indexNumber,
            ]);

            return ApiEnvelope::errorResponse(
                $removed ? 'This account is no longer in the system' : 'Index not found',
                404,
                $removed ? ['code' => 'student_removed'] : ['code' => 'student_not_found'],
                [
                    'in_system' => false,
                    'was_removed' => $removed !== null,
                    'removed_at' => $removed?->deleted_at?->toIso8601String(),
                ]
            );
        }

        if (empty($student->password)) {
            $settings = SystemSetting::get();
            if ($settings->require_password_on_first_login ?? true) {
                return ApiEnvelope::errorResponse('Set password first via web', 422, null, [
                    'needs_set_password' => true,
                    'index_number' => $student->index_number,
                ]);
            }
        } elseif (! $this->validatePassword($password, $student->password)) {
            Log::warning('api.v1.login.failed', ['reason' => 'bad_password', 'student_id' => $student->id]);

            return ApiEnvelope::errorResponse('Wrong password', 401);
        }

        $student->tokens()->where('name', 'mobile')->delete();
        $token = $student->createToken('mobile', ['*'])->plainTextToken;

        $user = StudentApiPayload::forUser($student);

        Log::info('api.v1.login.success', ['student_id' => $student->id]);

        return response()->json(ApiEnvelope::success([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(ApiEnvelope::success(null, 'Logged out'));
    }

    private function validatePassword(string $input, ?string $stored): bool
    {
        if (empty($stored)) {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($input, $stored);
        }

        return $input === $stored;
    }
}
