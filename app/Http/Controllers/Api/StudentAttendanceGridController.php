<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentAttendanceGridBuilder;
use App\Support\ApiEnvelope;
use App\Support\PasswordPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Per-student week-by-week attendance grid. Powers the mobile app
 * "attendance table" page (JSON) and the student-facing PDF export.
 */
class StudentAttendanceGridController extends Controller
{
    public function __construct(
        private readonly StudentAttendanceGridBuilder $builder,
    ) {}

    /**
     * GET /api/student/attendance-grid
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->authenticate($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return ApiEnvelope::success(
            $this->builder->build($student),
            'Attendance grid loaded',
        );
    }

    /**
     * GET /api/student/attendance-grid/pdf — same authn as the JSON
     * endpoint so the mobile app can fetch the PDF without bouncing
     * the user through a web login flow.
     */
    public function pdf(Request $request): Response
    {
        $student = $this->authenticate($request);
        if ($student instanceof JsonResponse) {
            // PDF endpoint still surfaces the auth error as JSON; the
            // app catches non-200 responses and shows the message.
            return response($student->getContent(), $student->getStatusCode(), [
                'Content-Type' => 'application/json',
            ]);
        }

        $data = $this->builder->build($student);
        $pdf = Pdf::loadView('student.pdf.attendance-grid', $data)
            ->setPaper('a4', 'portrait');

        $slug = \Illuminate\Support\Str::slug((string) $student->index_number);

        return $pdf->stream("attendance-{$slug}.pdf", ['Attachment' => false]);
    }

    private function authenticate(Request $request): Student|JsonResponse
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            $pat = PersonalAccessToken::findToken($bearer);
            if (! $pat || ! $pat->tokenable instanceof Student) {
                return ApiEnvelope::error('Invalid or expired token', 401);
            }

            return $pat->tokenable;
        }

        $index = $request->input('index_number') ?? $request->query('index_number');
        $password = $request->input('password') ?? $request->query('password');
        if (! is_string($index) || trim($index) === '' || ! is_string($password) || trim($password) === '') {
            return ApiEnvelope::error('index_number and password are required', 422);
        }

        $student = Student::findByIndex($index);
        if (! $student || ! PasswordPolicy::matches((string) $password, $student->password)) {
            return ApiEnvelope::error('Invalid credentials', 401);
        }

        return $student;
    }
}
