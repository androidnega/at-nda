<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * JSON API for institution-wide mobile dashboard layout themes (admin session).
 */
class MobileDashboardThemeController extends Controller
{
    private const REP_THEMES = ['classic', 'pastel_analytics', 'noir_task', 'team_reach', 'violet_calendar'];

    private const STUDENT_THEMES = ['classic', 'pastel_profile', 'noir_task', 'team_reach', 'violet_calendar'];

    public function show(): JsonResponse
    {
        $settings = SystemSetting::get();

        return response()->json([
            'rep_dashboard_theme' => $this->normalizedRep($settings),
            'student_dashboard_theme' => $this->normalizedStudent($settings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rep_dashboard_theme' => 'sometimes|required|in:'.implode(',', self::REP_THEMES),
            'student_dashboard_theme' => 'sometimes|required|in:'.implode(',', self::STUDENT_THEMES),
        ]);

        $settings = SystemSetting::get();
        $payload = [];

        if (SystemSetting::hasRepDashboardThemeColumn() && array_key_exists('rep_dashboard_theme', $validated)) {
            $payload['rep_dashboard_theme'] = $validated['rep_dashboard_theme'];
        }
        if (SystemSetting::hasStudentDashboardThemeColumn() && array_key_exists('student_dashboard_theme', $validated)) {
            $payload['student_dashboard_theme'] = $validated['student_dashboard_theme'];
        }

        if ($payload !== []) {
            $settings->update($payload);
            Cache::forget('api_v1_settings');
        }

        $settings->refresh();

        return response()->json([
            'success' => true,
            'rep_dashboard_theme' => $this->normalizedRep($settings),
            'student_dashboard_theme' => $this->normalizedStudent($settings),
        ]);
    }

    private function normalizedRep(SystemSetting $settings): string
    {
        if (! SystemSetting::hasRepDashboardThemeColumn()) {
            return 'classic';
        }
        $v = (string) ($settings->rep_dashboard_theme ?? 'classic');

        return in_array($v, self::REP_THEMES, true) ? $v : 'classic';
    }

    private function normalizedStudent(SystemSetting $settings): string
    {
        if (! SystemSetting::hasStudentDashboardThemeColumn()) {
            return 'classic';
        }
        $v = (string) ($settings->student_dashboard_theme ?? 'classic');

        return in_array($v, self::STUDENT_THEMES, true) ? $v : 'classic';
    }
}
