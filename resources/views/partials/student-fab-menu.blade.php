{{-- Floating mobile-only navigation FAB.
     Replaces the old bottom-nav strip + the side drawer for the
     mobile student experience. Tapping the "+" fans a row of
     destination chips out HORIZONTALLY to the left of the button,
     so the screen content above stays uncovered. Never visible
     on desktop — the sidebar still handles that.

     IMPORTANT: this partial is included at the BOTTOM of the body,
     after <head> has already streamed. That means @push('styles')
     would arrive too late to land inside @stack('styles'). We
     inline the <style> block here — browsers happily parse and
     apply style blocks that live in the body. --}}
<style>
    /* ── Idle state ──────────────────────────────────────────────
       Two stacked animations give the button "life" without being
       loud: a slow vertical bob (so it feels alive even when no
       one is touching it) and an outward halo pulse (so the eye
       knows it's tappable). Both stop the moment the menu opens. */
    @keyframes studentFabBob {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-3px); }
    }
    @keyframes studentFabHalo {
        0%   { box-shadow: 0 0 0 0   rgba(14, 165, 233, 0.45), 0 10px 22px rgba(14, 165, 233, 0.45); }
        70%  { box-shadow: 0 0 0 18px rgba(14, 165, 233, 0),   0 10px 22px rgba(14, 165, 233, 0.45); }
        100% { box-shadow: 0 0 0 0   rgba(14, 165, 233, 0),   0 10px 22px rgba(14, 165, 233, 0.45); }
    }
    #student-fab-toggle {
        animation:
            studentFabBob  2.6s ease-in-out infinite,
            studentFabHalo 1.8s ease-out     infinite;
        transition: transform 180ms cubic-bezier(.34, 1.56, .64, 1),
                    box-shadow 220ms ease;
    }
    /* ── Click ripple ────────────────────────────────────────────
       Press = brief squeeze, release = gentle settle. The bob
       animation above already controls transform, so we need
       !important here to beat it (per CSS animation precedence
       rules — author !important wins over keyframes). */
    #student-fab-toggle:active { transform: scale(0.88) !important; }

    /* ── Open state ──────────────────────────────────────────────
       Once open we kill the idle animations (no halo, no bob) so
       the user can focus on the menu they just summoned. */
    #student-fab-toggle.is-open {
        animation: none;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.32);
    }

    /* ── Backdrop · subtle depth when the menu is open ──────────
       Just a soft tint + a whisper of blur — enough to imply the
       chips are floating without obscuring the content under
       them. The vignette pulls toward the FAB corner so the
       focal point is unmistakable. */
    #student-fab-backdrop {
        background:
            radial-gradient(120% 90% at 100% 100%, rgba(2, 6, 23, 0.12), rgba(2, 6, 23, 0.22));
        -webkit-backdrop-filter: blur(0px);
                backdrop-filter: blur(0px);
        transition: opacity 220ms ease, backdrop-filter 260ms ease, -webkit-backdrop-filter 260ms ease;
    }
    #student-fab-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
        -webkit-backdrop-filter: blur(1.5px);
                backdrop-filter: blur(1.5px);
    }

    /* ── Items ──────────────────────────────────────────────────
       Hidden by default, then fan to the left when the menu is
       open. Each <li> overrides Tailwind's translate-x-3 /
       opacity-0 utilities via ID specificity + !important so the
       open state always wins. */
    #student-fab-items.is-open { pointer-events: auto; }
    #student-fab-items.is-open .fab-item {
        transform: translateX(0) !important;
        opacity: 1 !important;
    }
    .fab-item a:active { transform: scale(0.9); }
    .fab-item a {
        transition: transform 160ms cubic-bezier(.34, 1.56, .64, 1);
    }
</style>

<div id="student-fab-root" class="lg:hidden">

    {{-- Backdrop. Click anywhere outside to collapse the menu. --}}
    <div id="student-fab-backdrop"
         class="fixed inset-0 z-30 opacity-0 pointer-events-none"></div>

    {{-- Horizontal item row. Sits just to the left of the FAB,
         vertically anchored so each item's icon shares the same
         centreline as the FAB's "+".
         · FAB icon centre = bottom-5 (1.25rem) + 14/2 (1.75rem) = 3rem
         · Items are 12 (3rem) tall, so bottom-6 (1.5rem) puts the
           icon centre at 3rem too. flex-row-reverse keeps the
           closest chip nearest the FAB. --}}
    <ul id="student-fab-items"
        class="fixed right-[5.25rem] bottom-6 z-40 flex flex-row-reverse items-center gap-3 pointer-events-none">
        @php
            $fabLinks = [
                ['route' => 'dashboard.dashboard',        'label' => 'Home',      'icon' => 'fa-house',        'tint' => 'sky'],
                ['route' => 'dashboard.timetable',        'label' => 'Timetable', 'icon' => 'fa-calendar-alt', 'tint' => 'sky'],
                ['route' => 'student.attendance.history', 'label' => 'History',   'icon' => 'fa-chart-line',   'tint' => 'emerald'],
                ['route' => 'student.profile',            'label' => 'Profile',   'icon' => 'fa-circle-user',  'tint' => 'slate'],
            ];
            $tintClasses = [
                'sky'     => 'bg-sky-600 text-white',
                'emerald' => 'bg-emerald-600 text-white',
                'slate'   => 'bg-slate-800 text-white',
            ];
        @endphp
        @foreach($fabLinks as $i => $link)
            @if(\Illuminate\Support\Facades\Route::has($link['route']))
                {{-- relative wrapper so the label can be absolutely
                     positioned below the icon — that way the icon
                     itself stays exactly on the FAB centreline
                     regardless of label width. --}}
                <li class="fab-item relative translate-x-3 opacity-0 transition-all duration-200"
                    style="transition-delay: {{ $i * 50 }}ms;">
                    <a href="{{ route($link['route']) }}"
                       aria-label="{{ $link['label'] }}"
                       class="w-12 h-12 rounded-full {{ $tintClasses[$link['tint']] ?? $tintClasses['sky'] }} flex items-center justify-center shadow-lg shadow-slate-900/25 ring-2 ring-white">
                        <i class="fas {{ $link['icon'] }} text-sm"></i>
                    </a>
                    <span class="absolute left-1/2 -translate-x-1/2 top-full mt-1 whitespace-nowrap text-[10px] font-semibold text-white tracking-wide drop-shadow-md">
                        {{ $link['label'] }}
                    </span>
                </li>
            @endif
        @endforeach
    </ul>

    {{-- The "+" itself. Idle: gentle bob + halo pulse. Pressed:
         brief 0.88x squeeze that springs back. Open: animations
         stop, icon rotates 135° into an "×". --}}
    <button id="student-fab-toggle" type="button" aria-label="Open menu" aria-expanded="false"
            class="fixed right-4 bottom-5 z-50 w-14 h-14 rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center ring-4 ring-white dark:ring-slate-950">
        <span class="sr-only">Toggle navigation</span>
        <i id="student-fab-icon" class="fas fa-plus text-xl transition-transform duration-300"></i>
    </button>
</div>

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

    // Auto-close after a destination is tapped so users coming
    // back via the browser Back button don't land on a stuck
    // overlay.
    items.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
    });
})();
</script>
@endpush
