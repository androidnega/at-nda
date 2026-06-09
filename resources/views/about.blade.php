@php
    $name = 'Emmanuel Kofi Kwofie';
    $aka = 'Manuel';
    $appName = config('app.name', 'a-tenda');
    $signInUrl = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    {{-- Zoom-locked per the project-wide policy. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="description" content="Profile of {{ $name }} — software engineer, class representative, and Planning Committee Chair of the Faculty of Applied Sciences Student Association at Takoradi Technical University.">
    <meta property="og:title" content="{{ $name }} · {{ $appName }}">
    <meta property="og:description" content="Software engineer · Class representative · FASSA Planning Committee Chair. Selected projects include QuizSnap, KuukuaCares, and Kikam Tech.">
    <meta property="og:image" content="{{ asset('img/about/manuel.jpg') }}">
    <meta property="og:type" content="profile">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <title>About {{ $aka }} · {{ $appName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } }
        }
    </script>
    <style>
        html, body { background: #f8fafc; }
        @supports (padding: env(safe-area-inset-bottom)) {
            .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
            .pt-safe { padding-top: env(safe-area-inset-top); }
        }
        /* Soft animated halo behind the portrait — same vibe family as
           the FAB pulse, slow and quiet. */
        @keyframes manuelHalo {
            0%, 100% { box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.18); }
            50%      { box-shadow: 0 0 0 22px rgba(14, 165, 233, 0); }
        }
        .manuel-halo { animation: manuelHalo 3.4s ease-out infinite; }
        /* Page entry fade so the layout doesn't slam in. */
        @keyframes manuelFadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .manuel-enter { animation: manuelFadeUp 480ms ease-out both; }
    </style>
</head>
<body class="font-sans text-slate-900 antialiased min-h-screen pb-safe">

{{-- Top bar — minimal: just brand + back to sign-in. --}}
<header class="w-full px-4 sm:px-6 pt-safe">
    <div class="max-w-3xl mx-auto flex items-center justify-between py-3.5">
        <a href="{{ url('/') }}" class="flex items-center gap-2 text-slate-800 hover:text-slate-900">
            <span class="inline-flex w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-white items-center justify-center">
                <i class="fas fa-graduation-cap text-sm"></i>
            </span>
            <span class="font-bold text-sm tracking-tight">{{ $appName }}</span>
        </a>
        <a href="{{ $signInUrl }}"
           class="text-xs sm:text-sm font-semibold text-slate-700 hover:text-slate-900 inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 shadow-sm">
            <i class="fas fa-arrow-right-to-bracket text-[10px]"></i>
            <span>Sign in</span>
        </a>
    </div>
</header>

{{-- Hero · portrait + name --}}
<section class="px-4 sm:px-6">
    <div class="max-w-3xl mx-auto pt-6 sm:pt-10 manuel-enter">

        <div class="rounded-3xl bg-white border border-slate-200 shadow-[0_30px_60px_-30px_rgba(15,23,42,0.25),0_10px_25px_-15px_rgba(15,23,42,0.12)] overflow-hidden">
            <div class="grid sm:grid-cols-[auto,1fr] gap-0">

                {{-- Portrait. Loaded eagerly so the page feels populated
                     immediately; the source is already optimised
                     (600×750 JPEG, ~85 KB) so there's no bandwidth
                     concern on slow links. --}}
                <div class="p-5 sm:p-6 flex justify-center sm:justify-start">
                    <div class="relative">
                        <div class="absolute -inset-3 rounded-full bg-gradient-to-br from-sky-200/60 via-transparent to-indigo-200/60 blur-2xl"></div>
                        <div class="relative w-36 h-36 sm:w-40 sm:h-40 rounded-full overflow-hidden ring-4 ring-white shadow-xl manuel-halo">
                            <img src="{{ asset('img/about/manuel.jpg') }}"
                                 alt="Portrait of {{ $name }}"
                                 width="600" height="750"
                                 loading="eager" decoding="async"
                                 class="w-full h-full object-cover object-center">
                        </div>
                    </div>
                </div>

                {{-- Identity block --}}
                <div class="px-5 pb-5 sm:px-6 sm:pt-6 sm:pb-6 flex flex-col justify-center">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700">Profile</p>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
                        {{ $name }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Also known as <span class="font-semibold text-slate-800">{{ $aka }}</span>.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 text-sky-800 ring-1 ring-sky-200 px-2.5 py-1 text-[11px] font-semibold">
                            <i class="fas fa-user-graduate text-[10px]"></i> Class Representative · Group A
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 px-2.5 py-1 text-[11px] font-semibold">
                            <i class="fas fa-code text-[10px]"></i> Software Engineer
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-200 px-2.5 py-1 text-[11px] font-semibold">
                            <i class="fas fa-people-group text-[10px]"></i> FASSA · Planning Committee Chair
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- Studies + role · two-card row --}}
<section class="px-4 sm:px-6 mt-5 sm:mt-6 manuel-enter" style="animation-delay: 80ms">
    <div class="max-w-3xl mx-auto grid sm:grid-cols-2 gap-4">

        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center">
                    <i class="fas fa-university"></i>
                </span>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Education</p>
                    <p class="text-sm font-bold text-slate-900">BTECH, Information Technology · Level 200</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                Currently reading for a Bachelor of Technology in Information Technology at
                <span class="font-semibold text-slate-800">Takoradi Technical University</span>,
                under the Faculty of Applied Sciences, Department of Computer Science.
                Area of specialisation: <span class="font-semibold text-slate-800">Computer Software Engineering</span>.
            </p>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                    <i class="fas fa-handshake"></i>
                </span>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Leadership</p>
                    <p class="text-sm font-bold text-slate-900">FASSA · Planning Committee Chair</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                Serves as Planning Committee Chair for the
                <span class="font-semibold text-slate-800">Faculty of Applied Sciences Student Association (FASSA)</span>,
                with responsibility for coordinating faculty programmes, academic events,
                and student-welfare initiatives.
            </p>
        </div>

    </div>
</section>

{{-- Journey · timeline-flavoured prose --}}
<section class="px-4 sm:px-6 mt-5 sm:mt-6 manuel-enter" style="animation-delay: 160ms">
    <div class="max-w-3xl mx-auto rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center">
                <i class="fas fa-briefcase"></i>
            </span>
            <div>
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Professional Background</p>
                <p class="text-sm font-bold text-slate-900">Software Engineer · Active since 2016</p>
            </div>
        </div>
        <p class="mt-4 text-sm text-slate-700 leading-relaxed">
            Practising software engineer since <span class="font-semibold text-slate-900">2016</span>,
            with a near-decade of continuous delivery experience. Primary expertise spans
            <span class="font-semibold text-slate-900">full-stack web development</span> and
            <span class="font-semibold text-slate-900">mobile application development</span>.
            Engagements have included educational platforms, public-sector digital presences,
            and institutional websites — delivered with a focus on reliability,
            accessibility, and long-term maintainability.
        </p>
    </div>
</section>

{{-- Selected work · three project cards --}}
<section class="px-4 sm:px-6 mt-5 sm:mt-6 manuel-enter" style="animation-delay: 240ms">
    <div class="max-w-3xl mx-auto">

        <div class="flex items-end justify-between mb-3">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Selected Projects</h2>
            <p class="text-[11px] text-slate-500">A representative sample</p>
        </div>

        <div class="grid sm:grid-cols-3 gap-3">

            <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-50 to-white p-4 ring-1 ring-emerald-100 shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-sm"></i>
                    </span>
                    <p class="text-sm font-bold text-slate-900">QuizSnap</p>
                </div>
                <p class="text-[12px] text-slate-600 leading-relaxed">
                    An academic assessment platform for authoring and administering quizzes.
                    Built for rapid setup, straightforward invigilation, and consistent
                    performance across devices.
                </p>
            </div>

            <a href="https://kuukuacares.com" target="_blank" rel="noopener noreferrer"
               class="group rounded-2xl border border-slate-200 bg-gradient-to-br from-sky-50 to-white p-4 ring-1 ring-sky-100 shadow-sm hover:ring-sky-300 transition-all">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg bg-sky-600 text-white flex items-center justify-center">
                        <i class="fas fa-globe text-sm"></i>
                    </span>
                    <p class="text-sm font-bold text-slate-900 group-hover:text-sky-800">KuukuaCares</p>
                </div>
                <p class="text-[12px] text-slate-600 leading-relaxed">
                    Official digital platform for the Member of Parliament for the
                    Ahanta West Constituency. Available at
                    <span class="text-sky-700 underline decoration-sky-300 underline-offset-2">kuukuacares.com</span>.
                </p>
            </a>

            <a href="https://kikamtech.org" target="_blank" rel="noopener noreferrer"
               class="group rounded-2xl border border-slate-200 bg-gradient-to-br from-amber-50 to-white p-4 ring-1 ring-amber-100 shadow-sm hover:ring-amber-300 transition-all">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg bg-amber-600 text-white flex items-center justify-center">
                        <i class="fas fa-school text-sm"></i>
                    </span>
                    <p class="text-sm font-bold text-slate-900 group-hover:text-amber-800">Kikam Tech</p>
                </div>
                <p class="text-[12px] text-slate-600 leading-relaxed">
                    Institutional website for Kikam Technical Institute. Available at
                    <span class="text-amber-700 underline decoration-amber-300 underline-offset-2">kikamtech.org</span>.
                </p>
            </a>

        </div>
    </div>
</section>

{{-- CTA · sign in / back to home --}}
<section class="px-4 sm:px-6 mt-6 mb-10 manuel-enter" style="animation-delay: 320ms">
    <div class="max-w-3xl mx-auto rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 text-white p-5 sm:p-6 shadow-lg">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-wider text-sky-300 font-semibold">{{ $appName }}</p>
                <p class="text-base sm:text-lg font-bold mt-0.5">Engineered for academic communities.</p>
                <p class="text-sm text-slate-300 mt-1">Sign in with your student credentials to manage attendance and access course information.</p>
            </div>
            <a href="{{ $signInUrl }}"
               class="shrink-0 inline-flex items-center gap-2 rounded-xl bg-white text-slate-900 px-4 py-2.5 text-sm font-bold hover:bg-slate-100 transition">
                <i class="fas fa-arrow-right-to-bracket text-xs"></i>
                Proceed to sign in
            </a>
        </div>
    </div>
</section>

<footer class="px-4 sm:px-6 pb-6 text-center text-[11px] text-slate-500">
    © {{ now()->year }} {{ $appName }}. All rights reserved.
</footer>

</body>
</html>
