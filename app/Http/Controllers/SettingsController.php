<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = SystemSetting::get();

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enable_face_verification' => 'nullable|boolean',
            'enable_ip_binding' => 'nullable|boolean',
            'enable_qr' => 'nullable|boolean',
            'require_password_on_first_login' => 'nullable|boolean',
            'allow_multiple_index_on_device' => 'nullable|boolean',
            'face_match_threshold' => 'nullable|numeric|min:0.2|max:1.0',
        ]);

        $settings = SystemSetting::get();
        $settings->update([
            'enable_face_verification' => $request->boolean('enable_face_verification'),
            'enable_ip_binding' => $request->boolean('enable_ip_binding'),
            'enable_qr' => $request->boolean('enable_qr'),
            'require_password_on_first_login' => $request->boolean('require_password_on_first_login'),
            'allow_multiple_index_on_device' => $request->boolean('allow_multiple_index_on_device'),
            'face_match_threshold' => (float) ($validated['face_match_threshold'] ?? 0.5),
        ]);
        Cache::forget('api_v1_settings');

        return back()->with('success', 'Settings updated');
    }
}
