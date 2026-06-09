{{-- Floating mobile-only navigation FAB.
     Replaces the old bottom-nav strip + the side drawer for the
     mobile student experience. Tapping the "+" expands a stack of
     destination chips that fade up around the button. Long enough
     to fit Home / Mark / Timetable / History / Materials / Profile
     without crowding the bottom of the screen, and never visible
     on desktop (the sidebar is still there). --}}
<div id="student-fab-root" class="lg:hidden">

    {{-- Backdrop. Click anywhere outside the FAB to collapse the menu. --}}
    <div id="student-fab-backdrop"
         class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-[2px] opacity-0 pointer-events-none transition-opacity duration-200"></div>

    {{-- Stacked items. Each one slides up + fades in with a small
         per-item delay, then animates back down on close. We render
         them in reverse visual order so flex-col-reverse puts the
         first (Home) closest to the FAB. --}}
    <ul id="student-fab-items"
        class="fixed right-4 bottom-24 z-40 flex flex-col-reverse items-end gap-2.5 pointer-events-none">
        @php
            $fabLinks = [
                ['route' => 'dashboard.dashboard',        'label' => 'Dashboard',   'icon' => 'fa-house',         'tint' => 'sky'],
                ['route' => 'student.attendance.web',     'label' => 'Mark',        'icon' => 'fa-arrow-down-to-bracket', 'tint' => 'emerald'],
                ['route' => 'dashboard.timetable',        'label' => 'Timetable',   'icon' => 'fa-calendar-alt',  'tint' => 'sky'],
                ['route' => 'student.attendance.history', 'label' => 'History',     'icon' => 'fa-chart-line',    'tint' => 'sky'],
                ['route' => 'dashboard.materials.index',  'label' => 'Materials',   'icon' => 'fa-folder-open',   'tint' => 'emerald'],
                ['route' => 'student.profile',            'label' => 'Profile',     'icon' => 'fa-circle-user',   'tint' => 'slate'],
            ];
            $tintClasses = [
                'sky'     => 'bg-sky-600 text-white',
                'emerald' => 'bg-emerald-600 text-white',
                'slate'   => 'bg-slate-800 text-white',
            ];
        @endphp
        @foreach($fabLinks as $i => $link)
            @if(\Illuminate\Support\Facades\Route::has($link['route']))
                <li class="fab-item flex items-center gap-2 translate-y-3 opacity-0 transition-all duration-200"
                    style="transition-delay: {{ $i * 35 }}ms;">
                    <span class="rounded-full bg-white/95 text-slate-800 text-[12px] font-semibold px-3 py-1.5 shadow-md shadow-slate-900/10 ring-1 ring-slate-200">
                        {{ $link['label'] }}
                    </span>
                    <a href="{{ route($link['route']) }}"
                       class="w-11 h-11 rounded-full {{ $tintClasses[$link['tint']] ?? $tintClasses['sky'] }} flex items-center justify-center shadow-lg shadow-slate-900/15 ring-2 ring-white">
                        <i class="fas {{ $link['icon'] }} text-sm"></i>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>

    {{-- The "+" itself. Has a soft halo behind it that pulses on its
         own — drawing the eye without becoming distracting. When the
         menu is open the icon spins to an "x" to reinforce close. --}}
    <button id="student-fab-toggle" type="button" aria-label="Open menu" aria-expanded="false"
            class="fixed right-4 bottom-5 z-50 w-14 h-14 rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-xl shadow-sky-500/40 ring-4 ring-white dark:ring-slate-950 student-fab-pulse">
        <span class="sr-only">Toggle navigation</span>
        <i id="student-fab-icon" class="fas fa-plus text-xl transition-transform duration-300"></i>
    </button>
</div>

@push('styles')
<style>
    /* Soft halo + pulse on the FAB so it reads as "tap me" without
       being noisy. We pulse a transparent shadow ring at ~1.4s
       cadence (slower than animate-ping which feels nervous). The
       inner button stays steady. */
    @keyframes studentFabPulse {
        0%   { box-shadow: 0 0 0 0   rgba(14, 165, 233, 0.45), 0 8px 18px rgba(14, 165, 233, 0.45); }
        70%  { box-shadow: 0 0 0 18px rgba(14, 165, 233, 0),   0 8px 18px rgba(14, 165, 233, 0.45); }
        100% { box-shadow: 0 0 0 0   rgba(14, 165, 233, 0),   0 8px 18px rgba(14, 165, 233, 0.45); }
    }
    .student-fab-pulse {
        animation: studentFabPulse 1.6s ease-out infinite;
    }
    /* When the menu is open we stop the pulse — no need to keep
       drawing attention to a button that's already showing its
       state. */
    .student-fab-pulse.is-open { animation: none; }

    /* Open state for the stacked menu items. */
    #student-fab-items.is-open { pointer-events: auto; }
    #student-fab-items.is-open .fab-item {
        transform: translateY(0);
        opacity: 1;
    }

    /* Show the backdrop with a fade. */
    #student-fab-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const root     = document.getElementById('student-fab-root');
    const toggle   = document.getElementById('student-fab-toggle');
    const items    = document.getElementById('student-fab-items');
    const backdrop = document.getElementById('student-fab-backdrop');
    const icon     = document.getElementById('student-fab-icon');
    if (!root || !toggle || !items || !backdrop || !icon) return;

    function setOpen(open) {
        toggle.classList.toggle('is-open', open);
        items.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        icon.classList.toggle('fa-plus', !open);
        icon.classList.toggle('fa-xmark', open);
        icon.style.transform = open ? 'rotate(135deg)' : 'rotate(0)';
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = items.classList.contains('is-open');
        setOpen(!isOpen);
    });

    backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && items.classList.contains('is-open')) setOpen(false);
    });

    // Auto-close after a destination is tapped — the page will start
    // navigating immediately, but if it's the same-page link we still
    // want the menu to collapse so the user isn't left with a stuck
    // overlay.
    items.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
    });
})();
</script>
@endpush
