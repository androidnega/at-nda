<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @isset($repStudent)
        @if($repStudent->class_id)
            <meta name="broadcast-class-id" content="{{ $repStudent->class_id }}">
        @endif
    @endisset
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/realtime.js'])
    @endif
    @stack('head')
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT: '#0d9488', 50: '#f0fdfa', 100: '#ccfbf1', 500: '#0d9488', 600: '#0f766e' },
                    }
                }
            }
        }
    </script>
    @include('partials.minimal-ui')
    <style>
        .sidebar-overlay { @apply fixed inset-0 bg-black/40 z-40 lg:hidden; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-gray-900 antialiased font-sans">
    <div id="sidebar-overlay" class="sidebar-overlay hidden" aria-hidden="true"></div>

    <aside id="sidebar" class="fixed top-0 left-0 z-50 h-full w-64 bg-white border-r border-gray-200 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex flex-col h-full">
            <div class="p-4 border-b border-gray-100">
                <a href="{{ route('dashboard.dashboard') }}" class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                        <i class="fas fa-user-graduate text-sm"></i>
                    </span>
                    <span class="font-bold text-gray-800 text-base truncate">{{ config('app.name') }}</span>
                </a>
            </div>
            <nav class="flex-1 overflow-y-auto py-3 px-2.5">
                <p class="px-2.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Menu</p>
                <a href="{{ route('dashboard.dashboard') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.dashboard') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-home w-4 text-center text-xs"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('dashboard.session') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.session') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-play-circle w-4 text-center text-xs"></i>
                    <span>Open session</span>
                </a>
                <a href="{{ route('dashboard.my-class') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.my-class') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-layer-group w-4 text-center text-xs"></i>
                    <span>My class</span>
                </a>
                <a href="{{ route('dashboard.students.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.students.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-users w-4 text-center text-xs"></i>
                    <span>Students</span>
                </a>
                <a href="{{ route('dashboard.timetable') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ (request()->routeIs('dashboard.timetable') && ! request()->routeIs('dashboard.timetable.manage')) ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-calendar-alt w-4 text-center text-xs"></i>
                    <span>Timetable</span>
                </a>
                <a href="{{ route('dashboard.timetable.manage') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.timetable.manage') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-pen-to-square w-4 text-center text-xs"></i>
                    <span>Manage timetable</span>
                </a>
                <a href="{{ route('dashboard.class-attendance.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.class-attendance.index') || (request()->routeIs('dashboard.class-attendance.*') && !request()->routeIs('dashboard.class-attendance.audit-logs')) ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-clipboard-list w-4 text-center text-xs"></i>
                    <span>Attendance</span>
                </a>
                @if(\Illuminate\Support\Facades\Route::has('dashboard.class-attendance.audit-logs'))
                <a href="{{ route('dashboard.class-attendance.audit-logs') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.class-attendance.audit-logs') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-shield-halved w-4 text-center text-xs"></i>
                    <span>Audit log</span>
                </a>
                @endif
                <a href="{{ route('dashboard.materials.index') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg mb-0.5 text-sm {{ request()->routeIs('dashboard.materials.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas fa-folder-open w-4 text-center text-xs"></i>
                    <span>Materials</span>
                </a>
            </nav>
        </div>
    </aside>

    <div class="lg:pl-64 min-h-screen flex flex-col w-full min-w-0">
        <header class="sticky top-0 z-30 w-full bg-white border-b border-gray-200">
            <div class="flex items-center justify-between gap-3 w-full max-w-[100vw] px-4 sm:px-6 lg:px-8 py-2.5 sm:py-3 min-h-[3.25rem]">
                <button type="button" id="sidebar-toggle" class="lg:hidden p-2.5 -ml-1 rounded-lg text-gray-600 hover:bg-gray-100" aria-label="Toggle menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div class="flex-1 min-w-0">
                    @hasSection('header')
                        @yield('header')
                    @else
                        <span class="text-sm font-semibold text-gray-800 truncate">{{ $__env->yieldContent('title') }}</span>
                    @endif
                </div>
                <div class="relative shrink-0" id="rep-profile-wrap">
                    <button type="button" id="rep-profile-btn" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-gray-50 border border-transparent hover:border-gray-200">
                        @if($repStudent ?? null)
                            @if($repStudent->profileImageUrl())
                                <img src="{{ $repStudent->profileImageUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover border border-gray-200">
                            @else
                                <span class="h-9 w-9 rounded-full bg-gradient-to-br from-primary/80 to-teal-700 text-white flex items-center justify-center text-xs font-bold">{{ $repStudent->avatarInitials() }}</span>
                            @endif
                        @else
                            <span class="h-9 w-9 rounded-full bg-primary/15 text-primary flex items-center justify-center"><i class="fas fa-user text-sm"></i></span>
                        @endif
                        <i class="fas fa-chevron-down text-[10px] text-gray-400 hidden sm:block"></i>
                    </button>
                    <div id="rep-profile-menu" class="hidden absolute right-0 top-full mt-1.5 w-64 rounded-lg bg-white border border-gray-200 py-2 z-[60] overflow-hidden">
                        <div class="px-3 py-2.5 border-b border-gray-100">
                            @php
                                $rep = $repStudent ?? null;
                                $idx = $rep?->index_number ?? session('student_index');
                                $nameLine = $rep ? trim($rep->getDisplayName()) : '';
                            @endphp
                            @if($nameLine !== '')
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $nameLine }}</p>
                                <p class="text-xs text-gray-500 font-mono mt-0.5">{{ $idx ?? '—' }}</p>
                            @else
                                <p class="text-sm font-semibold text-gray-900 font-mono truncate">{{ $idx ?? '—' }}</p>
                            @endif
                            @if($rep?->department?->name)
                                <p class="text-[11px] text-gray-500 mt-1 truncate">{{ $rep->department->name }}</p>
                            @endif
                        </div>
                        <a href="{{ route('student.profile') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 font-medium">
                            <i class="fas fa-user-gear text-gray-400 w-4"></i> Profile
                        </a>
                        <form action="{{ route('student.logout') }}" method="POST" class="border-t border-gray-100 mt-1 pt-1">
                            @csrf
                            <button type="submit"
                                @if($studentSignOutBlocked ?? false) disabled title="{{ $studentSignOutBlockMessage }}" @endif
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left font-medium {{ ($studentSignOutBlocked ?? false) ? 'text-gray-400 cursor-not-allowed' : 'text-red-600 hover:bg-red-50' }}">
                                <i class="fas fa-right-from-bracket w-4"></i> Log out
                            </button>
                            @if($studentSignOutBlocked ?? false)
                                <p class="px-3 pb-2 text-[11px] leading-snug text-gray-500">{{ $studentSignOutBlockMessage }}</p>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 w-full min-w-0 max-w-[100vw] px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            @yield('content')
        </main>
    </div>

    <script>
        (function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');
            function open() { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
            function close() { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); document.body.style.overflow = ''; }
            toggle?.addEventListener('click', () => sidebar.classList.contains('-translate-x-full') ? open() : close());
            overlay?.addEventListener('click', close);
            window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { if (e.matches) close(); });

            const repBtn = document.getElementById('rep-profile-btn');
            const repMenu = document.getElementById('rep-profile-menu');
            const repWrap = document.getElementById('rep-profile-wrap');
            repBtn?.addEventListener('click', function(e) {
                e.stopPropagation();
                repMenu?.classList.toggle('hidden');
            });
            document.addEventListener('click', function() { repMenu?.classList.add('hidden'); });
            repWrap?.addEventListener('click', function(e) { e.stopPropagation(); });
        })();
    </script>

    @if($studentSignOutBlocked ?? false)
    {{-- Trap the device Back gesture while a class is in session so the rep
         cannot leave their dashboard to a cached login page and sign in as a
         different account during the attendance window. --}}
    <script>
        (function () {
            try {
                history.pushState({ atendaBackGuard: true }, '', location.href);
            } catch (e) { /* ignore */ }
            window.addEventListener('popstate', function () {
                try {
                    history.pushState({ atendaBackGuard: true }, '', location.href);
                } catch (e) { /* ignore */ }
                const msg = @json($studentSignOutBlockMessage ?? 'Stay on the dashboard until the class is over.');
                if (window.navigator.vibrate) { try { window.navigator.vibrate(40); } catch (e) {} }
                const toast = document.createElement('div');
                toast.textContent = msg;
                toast.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font:600 12px Inter,system-ui,sans-serif;z-index:9999;box-shadow:0 6px 20px rgba(15,23,42,.25);max-width:90vw;text-align:center;line-height:1.35';
                document.body.appendChild(toast);
                setTimeout(function () { toast.remove(); }, 2400);
            });
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) { window.location.reload(); }
            });
        })();
    </script>
    @endif

    @stack('scripts')
</body>
</html>
