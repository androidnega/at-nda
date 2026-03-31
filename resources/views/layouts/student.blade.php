<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @isset($student)
        @if($student->class_id)
            <meta name="broadcast-class-id" content="{{ $student->class_id }}">
        @endif
    @endisset
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/realtime.js'])
    @endif
    <meta name="theme-color" content="#0ea5e9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>@yield('title', config('app.name'))</title>
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
                        primary: { DEFAULT: '#0ea5e9', 500: '#0ea5e9', 600: '#0284c7' },
                    }
                }
            }
        }
    </script>
    @include('partials.minimal-ui')
    <style>
        @supports (padding: env(safe-area-inset-bottom)) {
            .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
            .pt-safe { padding-top: env(safe-area-inset-top); }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen min-h-[100dvh] text-gray-900 antialiased font-sans pb-safe">
    <div id="student-sidebar-overlay" class="fixed inset-0 z-40 bg-slate-900/30 lg:hidden hidden" aria-hidden="true"></div>

    <aside id="student-sidebar" class="fixed top-0 left-0 z-50 h-full w-[min(17rem,88vw)] max-w-sm bg-white border-r border-slate-200 flex flex-col -translate-x-full lg:translate-x-0">
        <div class="pt-safe px-4 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-sky-600 flex items-center justify-center text-white">
                <i class="fas fa-graduation-cap text-lg"></i>
            </div>
            <div class="min-w-0">
                <p class="font-bold text-slate-800 text-sm leading-tight truncate">{{ config('app.name') }}</p>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-0.5">
            <a href="{{ route('dashboard.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('dashboard.dashboard') ? 'bg-sky-50 text-sky-700' : 'text-slate-600 hover:bg-slate-50' }}">
                <i class="fas fa-house w-5 text-center text-sky-500"></i>
                Dashboard
            </a>
            <a href="{{ route('dashboard.timetable') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('dashboard.timetable') ? 'bg-sky-50 text-sky-700' : 'text-slate-600 hover:bg-slate-50' }}">
                <i class="fas fa-calendar-alt w-5 text-center text-sky-500"></i>
                Timetable
            </a>
            <a href="{{ route('student.attendance.web') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 {{ request()->routeIs('web.attendance.*') ? 'bg-emerald-50 text-emerald-800' : '' }}">
                <i class="fas fa-qrcode w-5 text-center text-emerald-500"></i>
                Mark attendance
            </a>
        </nav>
        <div class="p-3 border-t border-slate-100">
            <form method="POST" action="{{ route('student.logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-red-50 hover:text-red-700">
                    <i class="fas fa-right-from-bracket"></i>
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-[17rem] min-h-screen min-h-[100dvh] flex flex-col w-full min-w-0">
        <header class="sticky top-0 z-30 w-full pt-safe bg-white border-b border-slate-200">
            <div class="flex items-center gap-2 w-full max-w-[100vw] px-4 sm:px-6 lg:px-8 py-2.5 min-h-[3.25rem]">
                <button type="button" id="student-sidebar-toggle" class="lg:hidden shrink-0 p-2.5 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Open menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div class="flex-1 min-w-0">
                    @hasSection('breadcrumb')
                        @yield('breadcrumb')
                    @else
                        <span class="text-sm font-semibold text-slate-800 truncate block">{{ $__env->yieldContent('title') }}</span>
                    @endif
                </div>
                @isset($student)
                <div class="relative shrink-0" id="student-profile-dropdown">
                    <button type="button" id="student-profile-btn" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-lg hover:bg-slate-100 border border-transparent hover:border-slate-200">
                        @if($student->profileImageUrl())
                            <img src="{{ $student->profileImageUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover border border-slate-200">
                        @else
                            <span class="h-9 w-9 rounded-full bg-gradient-to-br from-sky-400 to-blue-600 text-white flex items-center justify-center text-xs font-bold">{{ $student->avatarInitials() }}</span>
                        @endif
                        <i class="fas fa-chevron-down text-[10px] text-slate-400 hidden sm:block"></i>
                    </button>
                    <div id="student-profile-menu" class="hidden absolute right-0 top-full mt-1.5 w-56 rounded-lg bg-white border border-slate-200 py-2 z-50 overflow-hidden">
                        <div class="px-3 py-2 border-b border-slate-100">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $student->getDisplayNameOrIndex() }}</p>
                            <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $student->index_number }}</p>
                            @if($student->department?->name)
                                <p class="text-[11px] text-slate-500 mt-1 truncate">{{ $student->department->name }}</p>
                            @endif
                        </div>
                        <a href="{{ route('student.profile') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fas fa-user-gear text-slate-400 w-4"></i> Profile settings
                        </a>
                        <form method="POST" action="{{ route('student.logout') }}" class="border-t border-slate-100 mt-1 pt-1">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 text-left">
                                <i class="fas fa-right-from-bracket w-4"></i> Log out
                            </button>
                        </form>
                    </div>
                </div>
                @endisset
            </div>
        </header>

        <main class="flex-1 w-full min-w-0 max-w-[100vw] px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
            @yield('content')
        </main>
    </div>

    <script>
        (function() {
            const sidebar = document.getElementById('student-sidebar');
            const overlay = document.getElementById('student-sidebar-overlay');
            const toggle = document.getElementById('student-sidebar-toggle');
            function open() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            function close() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
            toggle?.addEventListener('click', () => sidebar.classList.contains('-translate-x-full') ? open() : close());
            overlay?.addEventListener('click', close);
            window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { if (e.matches) close(); });

            const btn = document.getElementById('student-profile-btn');
            const menu = document.getElementById('student-profile-menu');
            const wrap = document.getElementById('student-profile-dropdown');
            btn?.addEventListener('click', function(e) {
                e.stopPropagation();
                menu?.classList.toggle('hidden');
            });
            document.addEventListener('click', function() { menu?.classList.add('hidden'); });
            wrap?.addEventListener('click', function(e) { e.stopPropagation(); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
