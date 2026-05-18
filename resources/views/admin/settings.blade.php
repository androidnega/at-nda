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
    <form action="{{ route('dashboard.settings.update') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
        @csrf

        @if(session()->has('admin_id'))
        <div class="space-y-4 pb-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Sign-in page hero image</h2>
            <p class="text-sm text-gray-500">Shown on student login, password, and first-time setup (dark overlay applied on the site).</p>

            <div class="flex flex-col sm:flex-row gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50/80">
                <div class="w-full sm:w-48 shrink-0 rounded-xl overflow-hidden border border-gray-900/40 shadow-lg shadow-black/20 ring-1 ring-white/10 bg-gray-950">
                    <div class="relative aspect-[4/3]">
                        <img src="{{ $authHeroPreviewUrl ?? \App\Support\AuthHeroImage::previewUrl() }}" alt="Login hero preview"
                            class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-br from-black/55 via-black/30 to-black/50"></div>
                    </div>
                </div>
                <div class="flex-1 space-y-3 min-w-0">
                    <p class="text-sm text-gray-700">
                        @if($authHeroUsingCustom ?? false)
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-xs font-medium">Custom upload active</span>
                        @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-gray-200 text-gray-700 text-xs font-medium">Default image</span>
                        @endif
                    </p>
                    <div>
                        <label for="auth_hero_image" class="block text-sm font-medium text-gray-700 mb-1">Upload new image</label>
                        <input type="file" id="auth_hero_image" name="auth_hero_image" accept="image/jpeg,image/png,image/webp"
                            class="w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary">
                        <p class="text-xs text-gray-500 mt-1">JPEG, PNG, or WebP. Max width 1280px, compressed to ≤320 KB.</p>
                    </div>
                    @if($authHeroUsingCustom ?? false)
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="hidden" name="remove_auth_hero_image" value="0">
                        <input type="checkbox" name="remove_auth_hero_image" value="1" class="rounded border-gray-300 text-primary focus:ring-primary">
                        Remove custom image (restore bundled default)
                    </label>
                    @endif
                </div>
            </div>
        </div>
        @endif

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

            @if(session()->has('admin_id') && \App\Models\SystemSetting::hasEnforceStudentLogoutLockColumn())
            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Enforce student logout lock in app</span>
                    <p class="text-sm text-gray-500 mt-0.5">When ON, student/class-rep sign-out follows the lock window. Lecturers can always sign out.</p>
                </div>
                <input type="hidden" name="enforce_student_logout_lock" value="0">
                <input type="checkbox" name="enforce_student_logout_lock" value="1" {{ ($settings->enforce_student_logout_lock ?? true) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>
            @endif

            <div class="p-4 rounded-xl border border-gray-100">
                <label for="face_match_threshold" class="font-medium text-gray-800 block mb-1">Face Match Threshold</label>
                <p class="text-sm text-gray-500 mb-2">Lower = stricter match (default 0.5). Range: 0.2–1.0</p>
                <input type="number" name="face_match_threshold" id="face_match_threshold" step="0.01" min="0.2" max="1"
                    value="{{ $settings->face_match_threshold }}"
                    class="w-32 border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
            </div>
        </div>


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
                    <option value="team_reach" {{ ($settings->rep_dashboard_theme ?? '') === 'team_reach' ? 'selected' : '' }}>Team Reach (new)</option>
                    <option value="violet_calendar" {{ ($settings->rep_dashboard_theme ?? '') === 'violet_calendar' ? 'selected' : '' }}>Violet calendar (new)</option>
                    <option value="midnight_control" {{ ($settings->rep_dashboard_theme ?? '') === 'midnight_control' ? 'selected' : '' }}>Midnight control (new)</option>
                </select>
            </div>

            <div class="p-4 rounded-xl border border-gray-100 space-y-3">
                <label for="student_dashboard_theme" class="font-medium text-gray-800 block">Student home</label>
                <select name="student_dashboard_theme" id="student_dashboard_theme"
                    class="w-full max-w-md border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="classic" {{ ($settings->student_dashboard_theme ?? 'classic') === 'classic' ? 'selected' : '' }}>Classic (original)</option>
                    <option value="pastel_profile" {{ ($settings->student_dashboard_theme ?? '') === 'pastel_profile' ? 'selected' : '' }}>Pastel profile</option>
                    <option value="noir_task" {{ ($settings->student_dashboard_theme ?? '') === 'noir_task' ? 'selected' : '' }}>Noir task (new)</option>
                    <option value="team_reach" {{ ($settings->student_dashboard_theme ?? '') === 'team_reach' ? 'selected' : '' }}>Team Reach (new)</option>
                    <option value="violet_calendar" {{ ($settings->student_dashboard_theme ?? '') === 'violet_calendar' ? 'selected' : '' }}>Violet calendar (new)</option>
                    <option value="midnight_control" {{ ($settings->student_dashboard_theme ?? '') === 'midnight_control' ? 'selected' : '' }}>Midnight control (new)</option>
                </select>
            </div>

            @if(\App\Models\SystemSetting::hasMobileAppThemeSeedColumn())
            <div class="p-4 rounded-xl border border-gray-100 space-y-3">
                <label for="mobile_app_theme_seed" class="font-medium text-gray-800 block">App color variant</label>
                <select name="mobile_app_theme_seed" id="mobile_app_theme_seed"
                    class="w-full max-w-md border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="teal" {{ ($settings->mobile_app_theme_seed ?? 'teal') === 'teal' ? 'selected' : '' }}>Teal (default)</option>
                    <option value="blue" {{ ($settings->mobile_app_theme_seed ?? '') === 'blue' ? 'selected' : '' }}>Blue</option>
                    <option value="indigo" {{ ($settings->mobile_app_theme_seed ?? '') === 'indigo' ? 'selected' : '' }}>Indigo</option>
                    <option value="emerald" {{ ($settings->mobile_app_theme_seed ?? '') === 'emerald' ? 'selected' : '' }}>Emerald</option>
                    <option value="rose" {{ ($settings->mobile_app_theme_seed ?? '') === 'rose' ? 'selected' : '' }}>Rose</option>
                    <option value="amber" {{ ($settings->mobile_app_theme_seed ?? '') === 'amber' ? 'selected' : '' }}>Amber</option>
                </select>
                <p class="text-xs text-gray-500">Applies one consistent app-wide feel across pages in light and dark mode.</p>
            </div>
            @endif
        </div>
        @endif

        @if(\App\Models\SystemSetting::hasAttendanceModeColumns())
        <div class="space-y-4 pt-2 border-t border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Attendance runtime mode</h2>
            <div class="p-4 rounded-xl border border-gray-100 space-y-3">
                <label for="attendance_mode" class="font-medium text-gray-800 block">Global attendance mode</label>
                <select name="attendance_mode" id="attendance_mode"
                    class="w-full max-w-md border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="instant" {{ ($settings->attendance_mode ?? 'instant') === 'instant' ? 'selected' : '' }}>Instant attendance</option>
                    <option value="checkin_checkout" {{ ($settings->attendance_mode ?? '') === 'checkin_checkout' ? 'selected' : '' }}>Check-in / Check-out</option>
                </select>
            </div>
            <div class="p-4 rounded-xl border border-gray-100 space-y-3">
                <label for="instant_mode_type" class="font-medium text-gray-800 block">Instant mode type</label>
                <select name="instant_mode_type" id="instant_mode_type"
                    class="w-full max-w-md border-2 border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary">
                    <option value="location" {{ ($settings->instant_mode_type ?? '') === 'location' ? 'selected' : '' }}>Location only</option>
                    <option value="location_qr" {{ ($settings->instant_mode_type ?? 'location_qr') === 'location_qr' ? 'selected' : '' }}>Location + QR</option>
                    <option value="wifi" {{ ($settings->instant_mode_type ?? '') === 'wifi' ? 'selected' : '' }}>Wi-Fi</option>
                </select>
                <p class="text-xs text-gray-500">Ignored automatically when global mode is Check-in / Check-out (that mode is always location-based).</p>
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
