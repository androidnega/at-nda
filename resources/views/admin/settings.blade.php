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
    <form action="{{ route('dashboard.settings.update') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6"
          data-settings-form>
        @csrf

        {{-- Tab navigation. All panels live in the same form so a single
             "Save Settings" still persists everything; the tabs just hide
             the panels you're not looking at. --}}
        <nav class="flex gap-1 overflow-x-auto -mx-1 px-1 border-b border-gray-200 pb-px" data-settings-tabs role="tablist">
            @php
                $tabs = [
                    ['key' => 'general',    'label' => 'General',    'icon' => 'fa-image',          'show' => session()->has('admin_id')],
                    ['key' => 'security',   'label' => 'Security',   'icon' => 'fa-shield-halved',  'show' => true],
                    ['key' => 'mobile',     'label' => 'Mobile app', 'icon' => 'fa-mobile-screen',  'show' => session()->has('admin_id') && \App\Models\SystemSetting::hasRepDashboardThemeColumn() && \App\Models\SystemSetting::hasStudentDashboardThemeColumn()],
                    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'fa-circle-check',   'show' => \App\Models\SystemSetting::hasAttendanceModeColumns()],
                    ['key' => 'email',      'label' => 'Email',      'icon' => 'fa-envelope',       'show' => session()->has('admin_id') && \App\Models\SystemSetting::hasMailColumns()],
                    ['key' => 'cache',      'label' => 'Cache',      'icon' => 'fa-bolt',           'show' => session()->has('admin_id') && \App\Support\SchemaFeatures::hasRedisSettings()],
                ];
            @endphp
            @foreach($tabs as $tab)
                @if($tab['show'])
                <button type="button" role="tab"
                    data-settings-tab="{{ $tab['key'] }}"
                    class="settings-tab-btn relative inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-800 whitespace-nowrap">
                    <i class="fas {{ $tab['icon'] }} text-xs"></i>
                    <span>{{ $tab['label'] }}</span>
                </button>
                @endif
            @endforeach
        </nav>

        @if(session()->has('admin_id'))
        <div class="space-y-4" data-settings-panel="general" hidden>
            <h2 class="text-lg font-semibold text-gray-800">Sign-in page hero image</h2>
            <p class="text-sm text-gray-500">Shown on student web sign-in and the mobile app login screen.</p>

            <div class="flex flex-col sm:flex-row gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50/80">
                <div class="w-full sm:w-48 shrink-0 rounded-xl overflow-hidden shadow-sm shadow-gray-200/80 bg-gray-100">
                    <div class="relative aspect-[3/2]">
                        <img src="{{ $authHeroPreviewUrl ?? \App\Support\AuthHeroImage::previewUrl() }}" alt="Login hero preview"
                            class="absolute inset-0 w-full h-full object-cover object-center">
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
                        <p class="text-xs text-gray-500 mt-1">JPEG, PNG, or WebP. Max width 1280px, compressed to ≤500 KB.</p>
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

        <div class="space-y-4" data-settings-panel="security" hidden>
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

            @if(session()->has('admin_id') && \App\Models\SystemSetting::hasAllowRepDeletionColumn())
            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-red-100 bg-red-50/40 hover:bg-red-50/60 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800 flex items-center gap-2">
                        <i class="fas fa-trash-can text-red-500/80 text-sm"></i>
                        Allow class reps to delete attendance
                    </span>
                    <p class="text-sm text-gray-500 mt-0.5">When OFF, only super admins can delete attendance. Every rep deletion is logged with the deleted row + reason.</p>
                </div>
                <input type="hidden" name="allow_rep_attendance_deletion" value="0">
                <input type="checkbox" name="allow_rep_attendance_deletion" value="1" {{ ($settings->allow_rep_attendance_deletion ?? false) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
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
        <div class="space-y-4" data-settings-panel="mobile" hidden>
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
        <div class="space-y-4" data-settings-panel="attendance" hidden>
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

        @if(session()->has('admin_id') && \App\Models\SystemSetting::hasMailColumns())
        <div class="space-y-4" data-settings-panel="email" hidden>
            <div>
                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-envelope text-primary/80"></i>
                    Email (SMTP) for password resets
                </h2>
                <p class="text-sm text-gray-500 mt-1">Set up an outbound SMTP mailbox so students can reset their password by email. Credentials are encrypted before storage.</p>
            </div>

            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition cursor-pointer">
                <div>
                    <span class="font-medium text-gray-800">Enable email delivery</span>
                    <p class="text-sm text-gray-500 mt-0.5">Turn on after the SMTP fields below are filled and tested.</p>
                </div>
                <input type="hidden" name="mail_enabled" value="0">
                <input type="checkbox" name="mail_enabled" value="1" {{ ($settings->mail_enabled ?? false) ? 'checked' : '' }}
                    class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
            </label>

            <div class="p-3 rounded-xl border border-emerald-200 bg-emerald-50/60 flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-emerald-900">Quick fill from cPanel:</span>
                <button type="button" data-mail-preset="ssl"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 text-white px-3 py-1.5 text-xs font-semibold hover:bg-emerald-800">
                    <i class="fas fa-shield-halved"></i> SSL/TLS (recommended · port 465)
                </button>
                <button type="button" data-mail-preset="starttls"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white text-emerald-800 px-3 py-1.5 text-xs font-medium hover:bg-emerald-50">
                    <i class="fas fa-lock-open"></i> STARTTLS (port 587)
                </button>
                <span class="text-[11px] text-emerald-900/70 ml-auto">Fills host, port, encryption, username and from address. The password is never auto-filled — type it below.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2 p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_host" class="font-medium text-gray-800 text-sm">SMTP host</label>
                    <input type="text" name="mail_host" id="mail_host" value="{{ old('mail_host', $settings->mail_host) }}"
                        placeholder="smtp.gmail.com / smtp-relay.brevo.com"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div class="p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_port" class="font-medium text-gray-800 text-sm">Port</label>
                    <input type="number" name="mail_port" id="mail_port" value="{{ old('mail_port', $settings->mail_port) }}"
                        min="1" max="65535" placeholder="587"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div class="p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_encryption" class="font-medium text-gray-800 text-sm">Encryption</label>
                    <select name="mail_encryption" id="mail_encryption"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary bg-white">
                        <option value="" {{ (string) ($settings->mail_encryption ?? '') === '' ? 'selected' : '' }}>None</option>
                        <option value="tls" {{ ($settings->mail_encryption ?? '') === 'tls' ? 'selected' : '' }}>STARTTLS (port 587)</option>
                        <option value="ssl" {{ ($settings->mail_encryption ?? '') === 'ssl' ? 'selected' : '' }}>SSL (port 465)</option>
                    </select>
                </div>
                <div class="sm:col-span-2 p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_username" class="font-medium text-gray-800 text-sm">Username</label>
                    <input type="text" name="mail_username" id="mail_username" value="{{ old('mail_username', $settings->mail_username) }}"
                        autocomplete="off"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div class="p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_password" class="font-medium text-gray-800 text-sm">
                        Password
                        @if(filled($settings->mail_password_encrypted ?? null))
                            <span class="ml-2 inline-flex items-center gap-1 text-[10px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-full px-2 py-0.5">
                                <i class="fas fa-check"></i> stored
                            </span>
                        @endif
                    </label>
                    <input type="password" name="mail_password" id="mail_password"
                        placeholder="{{ filled($settings->mail_password_encrypted ?? null) ? 'Leave blank to keep current' : 'app password / api key' }}"
                        autocomplete="new-password"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div class="sm:col-span-2 p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_from_address" class="font-medium text-gray-800 text-sm">From address</label>
                    <input type="email" name="mail_from_address" id="mail_from_address" value="{{ old('mail_from_address', $settings->mail_from_address) }}"
                        placeholder="no-reply@example.com"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div class="p-4 rounded-xl border border-gray-100 space-y-1.5">
                    <label for="mail_from_name" class="font-medium text-gray-800 text-sm">From name</label>
                    <input type="text" name="mail_from_name" id="mail_from_name" value="{{ old('mail_from_name', $settings->mail_from_name) }}"
                        placeholder="{{ config('app.name') }}"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>

            <div class="p-4 rounded-xl border border-amber-100 bg-amber-50/60">
                <label for="mail_test_to" class="block text-sm font-medium text-amber-900 mb-1.5">
                    <i class="fas fa-paper-plane text-amber-700/80 mr-1"></i> Send a test message
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="email" name="mail_test_to" id="mail_test_to" value="{{ old('mail_test_to') }}"
                        placeholder="you@example.com"
                        class="flex-1 border border-amber-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 bg-white">
                    <button type="submit" name="mail_action" value="test"
                        class="inline-flex items-center justify-center gap-1.5 bg-amber-700 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-amber-800 transition">
                        <i class="fas fa-flask text-xs"></i> Save &amp; test
                    </button>
                </div>
                <p class="text-[11px] text-amber-800/80 mt-1.5">Saves your changes first, then attempts to deliver one short test email.</p>
            </div>
        </div>
        @endif

        @if(session()->has('admin_id') && \App\Support\SchemaFeatures::hasRedisSettings())
        @php
            $redisProbe = \App\Support\RedisRuntimeConfig::lastAvailabilityResult();
            $redisKnownUnavailable = $redisProbe['status'] === 'unavailable';
            $redisCheckedAt = $redisProbe['checked_at'] ?? null;
            $redisExpiresAt = $redisProbe['expires_at'] ?? null;
            $redisCheckedHuman = $redisCheckedAt ? \Carbon\Carbon::createFromTimestamp($redisCheckedAt)->diffForHumans() : null;
        @endphp
        <div class="space-y-3" data-settings-panel="cache" hidden>
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-bolt text-rose-500/80"></i>
                Cache &amp; Redis (performance)
            </h3>
            <p class="text-xs text-gray-500 -mt-2">
                On shared hosting many simultaneous attendance scans can exhaust connection / file-cache pools.
                Switch the cache driver to Redis to clear the bottleneck. When Redis is unreachable the
                system silently falls back to the file driver, so reps and admins always have a slot to log in.
            </p>

            @if($redisKnownUnavailable)
                {{-- Calm, informational state — Redis genuinely isn't on
                     this host. Hides the entire Redis form so admins
                     stop seeing the same auto-configure error every
                     visit. They can force a re-probe if they enable
                     Redis later. --}}
                <div class="p-4 rounded-xl border border-sky-200 bg-sky-50/60 text-sky-900 flex flex-col sm:flex-row items-start gap-3">
                    <i class="fas fa-circle-info mt-0.5 text-sky-700 text-lg shrink-0"></i>
                    <div class="text-sm leading-snug flex-1 min-w-0">
                        <p class="font-semibold">Redis is not available on this host.</p>
                        <p class="text-sky-900/85 text-xs mt-1">
                            Your site is running on the <strong>database</strong> cache driver, which is the safe default and works on every shared-hosting plan.
                            We probed for Redis @if($redisCheckedHuman)<span class="text-sky-700">{{ $redisCheckedHuman }}</span>@endif and it wasn't reachable, so we'll skip Redis for the next 7 days to keep this page quiet.
                        </p>
                        @if(! empty($redisProbe['attempts']))
                            <details class="mt-2 text-[11px] text-sky-900/80">
                                <summary class="cursor-pointer font-semibold">What we tried</summary>
                                <ul class="mt-1 ml-4 list-disc space-y-0.5">
                                    @foreach($redisProbe['attempts'] as $attempt)
                                        <li><span class="font-mono">{{ $attempt['label'] ?? 'candidate' }} ({{ $attempt['host'] ?? '-' }}:{{ $attempt['port'] ?? 0 }})</span> → {{ $attempt['error'] ?? 'failed' }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="submit" name="redis_action" value="reprobe"
                                    formnovalidate
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-sky-300 bg-white px-3 py-1.5 text-xs font-semibold text-sky-800 hover:bg-sky-50">
                                <i class="fas fa-rotate text-[10px]"></i> Force re-probe Redis
                            </button>
                            <span class="text-[11px] text-sky-900/70">Click only if you've enabled Redis on your host since the last attempt.</span>
                        </div>
                    </div>
                </div>
            @elseif(\App\Support\RedisRuntimeConfig::isDegradedToFile())
                <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 flex items-start gap-2.5">
                    <i class="fas fa-triangle-exclamation mt-0.5 text-amber-700"></i>
                    <div class="text-sm leading-snug">
                        <p class="font-semibold">Redis is currently unreachable — running on file cache.</p>
                        <p class="text-amber-900/80 text-xs mt-1">
                            We auto-fell back to the file driver so the site stays up and reps / admins can keep logging in.
                            Once Redis is healthy again, run <strong>Auto-configure</strong> below to reconnect, or
                            switch the driver to <strong>database</strong> permanently.
                        </p>
                    </div>
                </div>
            @endif

            @unless($redisKnownUnavailable)
            <div class="p-4 rounded-xl border border-rose-100 bg-rose-50/30 space-y-3">
                <div>
                    <label for="cache_driver" class="font-medium text-gray-800 text-sm">Active cache driver</label>
                    <select name="cache_driver" id="cache_driver" class="mt-1 w-full sm:w-72 border border-rose-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-white">
                        @php $cacheDriver = old('cache_driver', $settings->cache_driver ?: 'database'); @endphp
                        <option value="database" {{ $cacheDriver === 'database' ? 'selected' : '' }}>database — safe default, works everywhere</option>
                        <option value="redis" {{ $cacheDriver === 'redis' ? 'selected' : '' }}>redis — fastest, recommended for &gt; 50 students</option>
                        <option value="file" {{ $cacheDriver === 'file' ? 'selected' : '' }}>file</option>
                        <option value="array" {{ $cacheDriver === 'array' ? 'selected' : '' }}>array — in-memory, single request only</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="redis_host" class="font-medium text-gray-800 text-sm">Redis host</label>
                        <input type="text" name="redis_host" id="redis_host"
                            value="{{ old('redis_host', $settings->redis_host) }}"
                            placeholder="127.0.0.1"
                            class="mt-1 w-full border border-rose-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-white">
                    </div>
                    <div>
                        <label for="redis_port" class="font-medium text-gray-800 text-sm">Port</label>
                        <input type="number" name="redis_port" id="redis_port" min="1" max="65535"
                            value="{{ old('redis_port', $settings->redis_port ?: 6379) }}"
                            class="mt-1 w-full border border-rose-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="redis_database" class="font-medium text-gray-800 text-sm">DB index</label>
                        <input type="number" name="redis_database" id="redis_database" min="0" max="15"
                            value="{{ old('redis_database', $settings->redis_database ?? 0) }}"
                            class="mt-1 w-full border border-rose-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="redis_prefix" class="font-medium text-gray-800 text-sm">Key prefix</label>
                        <input type="text" name="redis_prefix" id="redis_prefix"
                            value="{{ old('redis_prefix', $settings->redis_prefix) }}"
                            placeholder="atenda:"
                            class="mt-1 w-full border border-rose-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-white">
                    </div>
                </div>

                <div>
                    <label for="redis_password" class="font-medium text-gray-800 text-sm">Password (optional)</label>
                    <input type="password" name="redis_password" id="redis_password" autocomplete="new-password"
                        placeholder="{{ $settings->redis_password_encrypted ? '••••••••  (saved — leave blank to keep)' : 'leave blank if Redis has no auth' }}"
                        class="mt-1 w-full border border-rose-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-rose-300 focus:border-rose-400 bg-white">
                    @if($settings->redis_password_encrypted)
                    <label class="mt-1.5 inline-flex items-center gap-2 text-[11px] text-rose-700/90">
                        <input type="checkbox" name="clear_redis_password" value="1" class="w-3.5 h-3.5 rounded border-rose-300 text-rose-600 focus:ring-rose-400">
                        Clear saved password on save
                    </label>
                    @endif
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-rose-900">Not sure what to put here?</span>
                    <button type="submit" name="redis_action" value="auto"
                        formnovalidate
                        onclick="return confirm('Auto-configure will probe REDIS_URL, REDIS_HOST env vars, and 127.0.0.1:6379, then use whichever responds. Continue?');"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-rose-700 text-white px-3 py-1.5 text-xs font-semibold hover:bg-rose-800">
                        <i class="fas fa-wand-magic-sparkles"></i> Auto-configure (recommended)
                    </button>
                    <span class="text-[11px] text-rose-900/70 ml-auto">Probes the environment for a working Redis endpoint and switches the cache driver only when the ping succeeds.</span>
                </div>

                <div class="border-t border-rose-200/70 pt-3 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2">
                    <button type="submit" name="redis_action" value="test"
                        class="inline-flex items-center justify-center gap-1.5 bg-rose-700 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-rose-800 transition">
                        <i class="fas fa-plug text-xs"></i> Save &amp; ping Redis
                    </button>
                </div>
                <p class="text-[11px] text-rose-800/80">
                    Tip: on cPanel-style hosting ask your provider for a Redis socket (host + port).
                    On Render / Railway / fly.io paste the internal Redis URL parts here.
                </p>
                @php
                    $hasRedisClient = extension_loaded('redis') || class_exists(\Predis\Client::class);
                @endphp
                @if(! $hasRedisClient)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-[11px] text-amber-900 flex items-start gap-2">
                    <i class="fas fa-circle-exclamation mt-0.5"></i>
                    <span>
                        No Redis client detected on this server. To use Redis caching, run
                        <code class="px-1 py-0.5 rounded bg-amber-100 text-amber-900 font-mono">composer require predis/predis</code>
                        on the host (works on shared hosting), or enable the <code class="px-1 py-0.5 rounded bg-amber-100 text-amber-900 font-mono">phpredis</code> PHP extension. Until then the system falls back to the database driver.
                    </span>
                </div>
                @endif
            </div>
            @endunless
        </div>
        @endif

        <div class="pt-4 border-t border-gray-100">
            <button type="submit" name="mail_action" value="save" class="bg-primary text-white px-6 py-3 rounded-xl font-medium hover:bg-primary/90">
                Save Settings
            </button>
        </div>
    </form>
</div>

@push('scripts')
<style>
    [data-settings-tabs] .settings-tab-btn { border-bottom: 2px solid transparent; margin-bottom: -1px; }
    [data-settings-tabs] .settings-tab-btn.is-active {
        color: #0f766e; /* primary-ish teal */
        border-bottom-color: currentColor;
    }
    [data-settings-tabs] .settings-tab-btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.3);
        border-radius: 4px;
    }
</style>
<script>
    (function () {
        // ── Settings tab switcher ────────────────────────────────────────
        const STORAGE_KEY = 'atenda.settings.activeTab';
        const tabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
        if (tabs.length && panels.length) {
            function activate(key, save) {
                let matched = false;
                panels.forEach(p => {
                    const isMatch = p.getAttribute('data-settings-panel') === key;
                    p.hidden = !isMatch;
                    if (isMatch) matched = true;
                });
                tabs.forEach(t => t.classList.toggle('is-active', t.getAttribute('data-settings-tab') === key));
                if (save && matched) {
                    try { localStorage.setItem(STORAGE_KEY, key); } catch (e) {}
                }
                return matched;
            }
            const validKeys = new Set(panels.map(p => p.getAttribute('data-settings-panel')));

            tabs.forEach(t => t.addEventListener('click', () => activate(t.getAttribute('data-settings-tab'), true)));

            // Boot order: ?tab=, hash (#email), saved, then first available.
            const params = new URLSearchParams(window.location.search);
            const fromQuery = params.get('tab');
            const fromHash = (window.location.hash || '').replace('#', '');
            let saved = null;
            try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
            const candidates = [fromQuery, fromHash, saved, panels[0]?.getAttribute('data-settings-panel')];
            for (const c of candidates) {
                if (c && validKeys.has(c) && activate(c, false)) break;
            }

            // After validation/save reloads, jump to whichever tab the user
            // was last using so they keep their context.
            const form = document.querySelector('[data-settings-form]');
            if (form) {
                form.addEventListener('submit', () => {
                    const active = document.querySelector('[data-settings-tab].is-active');
                    if (active) {
                        try { localStorage.setItem(STORAGE_KEY, active.getAttribute('data-settings-tab')); } catch (e) {}
                    }
                });
            }
        }

        // Quick-fill presets for the SMTP form, derived from the cPanel
        // mailbox the operator pasted into the brief. The password field
        // is intentionally never touched — it must always be re-entered.
        const PRIMARY_DOMAIN = @json(parse_url(config('app.url'), PHP_URL_HOST) ?: 'at-enda.manuelcode.info');
        const FROM_NAME = @json(config('app.name'));

        const presets = {
            ssl: {
                host: PRIMARY_DOMAIN,
                port: 465,
                encryption: 'ssl',
                username: 'reset@' + PRIMARY_DOMAIN,
                from_address: 'reset@' + PRIMARY_DOMAIN,
            },
            starttls: {
                host: 'mail.' + PRIMARY_DOMAIN,
                port: 587,
                encryption: 'tls',
                username: 'reset@' + PRIMARY_DOMAIN,
                from_address: 'reset@' + PRIMARY_DOMAIN,
            },
        };

        function setField(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            el.value = value;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.querySelectorAll('[data-mail-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const preset = presets[btn.getAttribute('data-mail-preset')];
                if (!preset) return;
                setField('mail_host', preset.host);
                setField('mail_port', preset.port);
                setField('mail_encryption', preset.encryption);
                setField('mail_username', preset.username);
                setField('mail_from_address', preset.from_address);

                const fromName = document.getElementById('mail_from_name');
                if (fromName && !fromName.value.trim()) {
                    fromName.value = FROM_NAME || 'at-enda';
                }

                const enable = document.querySelector('input[name="mail_enabled"][type="checkbox"]');
                if (enable) enable.checked = true;

                const passwordInput = document.getElementById('mail_password');
                if (passwordInput) {
                    passwordInput.focus();
                    passwordInput.placeholder = 'Type the mailbox password to finish';
                }
            });
        });
    })();
</script>
@endpush
@endsection
