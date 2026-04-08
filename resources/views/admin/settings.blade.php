@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">System Settings</h1>
    <p class="text-gray-600 text-sm mt-1">Control face verification, IP binding, and device restrictions</p>
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-xl">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <form action="{{ route('dashboard.settings.update') }}" method="POST" class="p-6 space-y-6">
        @csrf

        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-800">Face & Device Security</h2>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Require Face Verification on Web Attendance</span>
                    <p class="text-sm text-gray-500 mt-0.5">When ON, students must pass face match before web attendance is marked</p>
                </div>
                <input type="hidden" name="enable_face_verification" value="0">
                <input type="checkbox" name="enable_face_verification" value="1" {{ ($settings->enable_face_verification ?? true) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>


            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Enable IP Binding</span>
                    <p class="text-sm text-gray-500 mt-0.5">Lock student to first device/IP used</p>
                </div>
                <input type="hidden" name="enable_ip_binding" value="0">
                <input type="checkbox" name="enable_ip_binding" value="1" {{ $settings->enable_ip_binding ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Enable QR Code Attendance</span>
                    <p class="text-sm text-gray-500 mt-0.5">Allow QR code scanning for attendance</p>
                </div>
                <input type="hidden" name="enable_qr" value="0">
                <input type="checkbox" name="enable_qr" value="1" {{ ($settings->enable_qr ?? true) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Require Password on First Login</span>
                    <p class="text-sm text-gray-500 mt-0.5">Students must create a password before using the app</p>
                </div>
                <input type="hidden" name="require_password_on_first_login" value="0">
                <input type="checkbox" name="require_password_on_first_login" value="1" {{ ($settings->require_password_on_first_login ?? true) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Require Profile Photo During Onboarding</span>
                    <p class="text-sm text-gray-500 mt-0.5">When OFF, students can access after name + phone without uploading a picture</p>
                </div>
                <input type="hidden" name="require_profile_image_on_onboarding" value="0">
                <input type="checkbox" name="require_profile_image_on_onboarding" value="1" {{ ($settings->require_profile_image_on_onboarding ?? true) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Allow Multiple Index on Same Device</span>
                    <p class="text-sm text-gray-500 mt-0.5">Let students switch index numbers on the same device</p>
                </div>
                <input type="hidden" name="allow_multiple_index_on_device" value="0">
                <input type="checkbox" name="allow_multiple_index_on_device" value="1" {{ $settings->allow_multiple_index_on_device ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>

            <div class="p-4 rounded-xl border border-gray-100">
                <label for="face_match_threshold" class="font-medium text-gray-800 block mb-1">Face Match Threshold</label>
                <p class="text-sm text-gray-500 mb-2">Lower = stricter match (default 0.5). Range: 0.2–1.0</p>
                <input type="number" name="face_match_threshold" id="face_match_threshold" step="0.01" min="0.2" max="1"
                    value="{{ $settings->face_match_threshold }}"
                    class="w-32 border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
            </div>
        </div>

        @if(session()->has('admin_id'))
        <div class="space-y-4 pt-2">
            <h2 class="text-lg font-semibold text-gray-800">Mobile diagnostics & privacy</h2>
            <p class="text-sm text-gray-500">When disabled, the API returns <code class="text-xs bg-gray-100 px-1 rounded">403 logging_disabled</code> and no new SMS/call rows are stored. The mobile app must still obtain explicit user consent before sending any payload.</p>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Enable SMS &amp; call log ingestion</span>
                    <p class="text-sm text-gray-500 mt-0.5">Allow authenticated students to sync optional logs. Review: <a href="{{ route('dashboard.communication-logs.sms.index') }}" class="text-primary underline">SMS &amp; call logs</a>.</p>
                </div>
                <input type="hidden" name="enable_sms_call_logging" value="0">
                <input type="checkbox" name="enable_sms_call_logging" value="1" {{ ($settings->enable_sms_call_logging ?? false) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>
        </div>
        @endif

        @if(session()->has('admin_id') && \App\Models\SystemSetting::hasRepDashboardThemeColumn() && \App\Models\SystemSetting::hasStudentDashboardThemeColumn())
        <div class="space-y-4 pt-2 border-t border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Mobile app dashboards</h2>
            <p class="text-sm text-gray-500">Choose the layout students and class reps see in the Flutter app (classic stays the default).</p>

            <div class="p-4 rounded-xl border border-gray-100 space-y-3">
                <label for="rep_dashboard_theme" class="font-medium text-gray-800 block">Class rep home</label>
                <select name="rep_dashboard_theme" id="rep_dashboard_theme"
                    class="w-full max-w-md border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="classic" {{ ($settings->rep_dashboard_theme ?? 'classic') === 'classic' ? 'selected' : '' }}>Classic (original)</option>
                    <option value="pastel_analytics" {{ ($settings->rep_dashboard_theme ?? '') === 'pastel_analytics' ? 'selected' : '' }}>Pastel analytics</option>
                    <option value="noir_task" {{ ($settings->rep_dashboard_theme ?? '') === 'noir_task' ? 'selected' : '' }}>Noir task (new)</option>
                </select>
            </div>

            <div class="p-4 rounded-xl border border-gray-100 space-y-3">
                <label for="student_dashboard_theme" class="font-medium text-gray-800 block">Student home</label>
                <select name="student_dashboard_theme" id="student_dashboard_theme"
                    class="w-full max-w-md border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="classic" {{ ($settings->student_dashboard_theme ?? 'classic') === 'classic' ? 'selected' : '' }}>Classic (original)</option>
                    <option value="pastel_profile" {{ ($settings->student_dashboard_theme ?? '') === 'pastel_profile' ? 'selected' : '' }}>Pastel profile</option>
                    <option value="noir_task" {{ ($settings->student_dashboard_theme ?? '') === 'noir_task' ? 'selected' : '' }}>Noir task (new)</option>
                </select>
            </div>
        </div>
        @endif

        <div class="pt-4 border-t border-gray-100">
            <button type="submit" class="bg-primary text-white px-6 py-3 rounded-xl font-medium hover:bg-primary/90">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection
