<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class NotificationsController extends Controller
{
    /**
     * Fetch pending in-app notifications for a student (no Firebase required).
     *
     * POST /api/notifications/pending
     * Body: { index_number: string, password: string }
     *
     * Response:
     * {
     *   success: true,
     *   message: "...",
     *   data: { notifications: [ { id, kind, title, body, starts_at, created_at } ] }
     * }
     */
    public function pending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string',
            'password' => 'required|string',
        ]);

        $indexUpper = strtoupper(trim($validated['index_number']));
        $password = (string) $validated['password'];

        // Sargable lookup via the UNIQUE index on `index_number`.
        $student = Student::findByIndex($indexUpper);
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
                'data' => ['notifications' => []],
            ], 404);
        }

        if (! $this->validatePassword($password, $student->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Wrong password',
                'data' => ['notifications' => []],
            ], 401);
        }

        $notifications = InAppNotification::query()
            ->where('student_id', $student->id)
            ->whereNull('read_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $now = Carbon::now();
        if ($notifications->isNotEmpty()) {
            InAppNotification::query()
                ->whereIn('id', $notifications->pluck('id')->all())
                ->update(['read_at' => $now]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications fetched',
            'data' => [
                'notifications' => $notifications->map(function (InAppNotification $n) {
                    return [
                        'id' => (int) $n->id,
                        'kind' => (string) $n->kind,
                        'title' => (string) $n->title,
                        'body' => (string) $n->body,
                        'starts_at' => $n->starts_at?->toIso8601String(),
                        'created_at' => $n->created_at?->toIso8601String(),
                    ];
                })->values()->all(),
            ],
        ]);
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
