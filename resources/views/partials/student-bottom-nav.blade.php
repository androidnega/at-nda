{{-- Floating mobile-only bottom navigation. Included automatically by
     layouts/student.blade.php so every student page gets the same
     quick-access bar without each view repeating the markup.
     Highlights the current section via request()->routeIs(). --}}
<div class="fixed bottom-0 inset-x-0 z-30 lg:hidden">
    <div class="mx-auto max-w-md px-4 pb-safe pb-3">
        <div class="relative flex items-end justify-between rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-lg shadow-slate-900/5 dark:shadow-black/40 px-3 pt-2 pb-2">
            <a href="{{ route('dashboard.dashboard') }}"
               class="flex-1 flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('dashboard.dashboard') ? 'text-sky-700 dark:text-sky-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <i class="fas fa-house text-base"></i>
                <span class="text-[10px] font-bold">Home</span>
            </a>
            <a href="{{ route('student.attendance.history') }}"
               class="flex-1 flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('student.attendance.history') ? 'text-sky-700 dark:text-sky-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <i class="fas fa-chart-line text-base"></i>
                <span class="text-[10px] font-bold">History</span>
            </a>
            <div class="relative -mt-7 mx-1">
                <a href="{{ route('student.attendance.web') }}" aria-label="Mark attendance"
                   class="flex items-center justify-center w-14 h-14 rounded-full bg-amber-400 text-slate-900 shadow-lg shadow-amber-400/40 dark:shadow-amber-500/20 ring-4 ring-white dark:ring-slate-900 hover:bg-amber-300 transition-colors">
                    <i class="fas fa-qrcode text-xl"></i>
                </a>
            </div>
            <a href="{{ route('dashboard.materials.index') }}"
               class="flex-1 flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('dashboard.materials.*') ? 'text-sky-700 dark:text-sky-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <i class="fas fa-folder-open text-base"></i>
                <span class="text-[10px] font-bold">Materials</span>
            </a>
            <a href="{{ route('student.profile') }}"
               class="flex-1 flex flex-col items-center gap-0.5 py-1 {{ request()->routeIs('student.profile*') ? 'text-sky-700 dark:text-sky-400' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <i class="fas fa-circle-user text-base"></i>
                <span class="text-[10px] font-bold">Profile</span>
            </a>
        </div>
    </div>
</div>
