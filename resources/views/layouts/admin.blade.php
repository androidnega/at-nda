<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-viewport-lock">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#e11d48', 50: '#fff1f2', 100: '#ffe4e6', 500: '#e11d48', 600: '#be123c' },
                    }
                }
            }
        }
    </script>
    @include('partials.minimal-ui')
    @include('partials.viewport-lock-styles')
    {{-- Per-page extra <head> additions pushed via @push('styles')
         (e.g. Chart.js, Leaflet, page-specific CSS). --}}
    @stack('styles')
    @stack('head')
    <style>
        .sidebar-overlay { @apply fixed inset-0 bg-black/40 z-40 lg:hidden; }
    </style>
</head>
<body class="bg-[#f3f5f8] text-slate-900 antialiased font-sans">
    {{-- Mobile sidebar overlay --}}
    <div id="sidebar-overlay" class="sidebar-overlay hidden" aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed top-0 left-0 z-50 h-full w-64 bg-[#f7f8fb] border-r border-slate-200 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex flex-col h-full">
            <div class="p-5 border-b border-slate-200">
                <a href="{{ route('dashboard.dashboard') }}" class="flex items-center gap-2.5">
                    <span class="w-10 h-10 rounded-xl bg-sky-100 flex items-center justify-center text-sky-600">
                        <i class="fas fa-clipboard-check text-lg"></i>
                    </span>
                    <span class="font-bold text-slate-800 text-lg">{{ config('app.name') }}</span>
                </a>
            </div>
            <nav class="flex-1 overflow-y-auto py-4 px-3">
                @php $dashboardRole = $dashboardRole ?? 'admin'; @endphp
                @php
                    $isLecturerView = session()->has('lecturer_id') && !session()->has('admin_id');
                @endphp
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">{{ $isLecturerView ? 'Lecturer' : 'Main menu' }}</p>
                <a href="{{ route('dashboard.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.dashboard') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-th-large w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                @if($isLecturerView)
                <a href="{{ route('dashboard.my-classes.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.my-classes.*') || request()->routeIs('dashboard.classes.show') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-layer-group w-5 text-center"></i>
                    <span>Class rosters</span>
                </a>
                <a href="{{ route('dashboard.students.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.students.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-user-graduate w-5 text-center"></i>
                    <span>Students</span>
                </a>
                <a href="{{ route('dashboard.teaching.attendance.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.teaching.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-clipboard-check w-5 text-center"></i>
                    <span>Attendance</span>
                </a>
                <a href="{{ route('dashboard.materials.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.materials.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-folder-open w-5 text-center"></i>
                    <span>Course materials</span>
                </a>
                @endif
                @if(!$isLecturerView)
                <a href="{{ route('dashboard.classes.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.classes.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-layer-group w-5 text-center"></i>
                    <span>Classes</span>
                </a>
                <a href="{{ route('dashboard.students.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.students.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-user-graduate w-5 text-center"></i>
                    <span>Students</span>
                </a>
                <a href="{{ route('dashboard.universities.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.universities.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-school w-5 text-center"></i>
                    <span>Schools</span>
                </a>
                <a href="{{ route('dashboard.semesters.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.semesters.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-calendar-alt w-5 text-center"></i>
                    <span>Semesters</span>
                </a>
                <a href="{{ route('dashboard.courses.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.courses.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-book w-5 text-center"></i>
                    <span>Courses</span>
                </a>
                <a href="{{ route('dashboard.attendance-weeks.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.attendance-weeks.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-calendar-week w-5 text-center"></i>
                    <span>Attendance reset</span>
                </a>
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3 mt-6">Settings</p>
                @if(session()->has('admin_id'))
                <a href="{{ route('dashboard.staff-accounts.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.staff-accounts.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-users-cog w-5 text-center"></i>
                    <span>User management</span>
                </a>
                @endif
                <a href="{{ route('dashboard.venues.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.venues.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-map-marker-alt w-5 text-center"></i>
                    <span>Venues</span>
                </a>
                <a href="{{ route('dashboard.lecturers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.lecturers.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-chalkboard-teacher w-5 text-center"></i>
                    <span>Lecturers</span>
                </a>
                <a href="{{ route('dashboard.settings.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.settings.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span>Settings</span>
                </a>
                @if(\Illuminate\Support\Facades\Route::has('dashboard.suspicious-attendances'))
                <a href="{{ route('dashboard.suspicious-attendances') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.suspicious-attendances') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-triangle-exclamation w-5 text-center"></i>
                    <span>Suspicious attendance</span>
                </a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('dashboard.audit-logs.index'))
                <a href="{{ route('dashboard.audit-logs.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-1 border border-transparent {{ request()->routeIs('dashboard.audit-logs.*') ? 'bg-sky-500 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:border-slate-200' }}">
                    <i class="fas fa-shield-halved w-5 text-center"></i>
                    <span>Audit logs</span>
                </a>
                @endif
                @endif
            </nav>
        </div>
    </aside>

    {{-- Main content wrapper --}}
    <div class="app-dashboard-shell lg:pl-64 w-full min-w-0">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 w-full bg-white/95 border-b border-slate-200">
            <div class="flex items-center justify-between gap-3 w-full max-w-[100vw] px-4 sm:px-6 lg:px-8 py-3 min-h-[3.25rem]">
                <button type="button" id="sidebar-toggle" class="lg:hidden p-2 -ml-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Toggle menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div class="flex-1 min-w-0"></div>
                @php
                    $isLecturerView = session()->has('lecturer_id') && !session()->has('admin_id');
                    $user = $user ?? (session()->has('admin_id') ? \App\Models\User::find(session('admin_id')) : null);
                    $lecturerProfile = $isLecturerView ? \App\Models\Lecturer::find(session('lecturer_id')) : null;
                    // Normalize lecturer names so block-capital or all-lowercase
                    // imports still render as "Mr. Joseph Danso", not "MR. JOSEPH DANSO".
                    $lecturerProfileName = $isLecturerView && $lecturerProfile
                        ? (method_exists($lecturerProfile, 'displayName') ? $lecturerProfile->displayName() : $lecturerProfile->name)
                        : null;
                    $profileName = $isLecturerView ? ($lecturerProfileName ?: 'Lecturer') : ($user?->name ?? 'Administrator');
                    $profileEmail = $isLecturerView ? ($lecturerProfile?->email ?? 'Staff dashboard') : ($user?->email ?? 'Staff dashboard');
                    $logoutRoute = $isLecturerView ? route('lecturer.logout') : route('admin.logout');
                @endphp
                <div class="relative shrink-0" id="staff-profile-wrap">
                    <button type="button" id="staff-profile-btn" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200">
                        @if($profileName)
                            <span class="h-9 w-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">{{ strtoupper(substr($profileName, 0, 1)) }}</span>
                        @else
                            <span class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                <i class="fas fa-user text-sm"></i>
                            </span>
                        @endif
                        <i class="fas fa-chevron-down text-[10px] text-gray-400 hidden sm:block"></i>
                    </button>
                    <div id="staff-profile-menu" class="hidden absolute right-0 top-full mt-1.5 w-56 rounded-lg bg-white border border-gray-200 py-2 z-[60] overflow-hidden">
                        <div class="px-3 py-2.5 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $profileName }}</p>
                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ $profileEmail }}</p>
                        </div>
                        @if(!$isLecturerView)
                            <a href="{{ route('dashboard.profile.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-user-gear text-gray-400 w-4"></i> Profile
                            </a>
                        @else
                            <a href="{{ route('lecturer.password.change.form') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-key text-gray-400 w-4"></i> Change password
                            </a>
                        @endif
                        <form action="{{ $logoutRoute }}" method="POST" class="border-t border-gray-100 mt-1 pt-1">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 text-left font-medium">
                                <i class="fas fa-right-from-bracket w-4"></i> Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="app-dashboard-main w-full min-w-0 max-w-[100vw] px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            @yield('content')
        </main>
    </div>

    <script>
        (function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');
            function open() { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); }
            function close() { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); }
            toggle?.addEventListener('click', () => sidebar.classList.contains('-translate-x-full') ? open() : close());
            overlay?.addEventListener('click', close);
            window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { if (e.matches) close(); });

            const staffBtn = document.getElementById('staff-profile-btn');
            const staffMenu = document.getElementById('staff-profile-menu');
            const staffWrap = document.getElementById('staff-profile-wrap');
            staffBtn?.addEventListener('click', function(e) {
                e.stopPropagation();
                staffMenu?.classList.toggle('hidden');
            });
            document.addEventListener('click', function() { staffMenu?.classList.add('hidden'); });
            staffWrap?.addEventListener('click', function(e) { e.stopPropagation(); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
