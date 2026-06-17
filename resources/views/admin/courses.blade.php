@extends('layouts.admin')

@section('title', 'Courses')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold">Courses</h1>
        <p class="text-gray-600 text-sm mt-1">Manage courses</p>
    </div>
    <a href="{{ route('dashboard.courses.create') }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700 inline-flex items-center justify-center shrink-0">
        Create Course
    </a>
</div>

@if (session('success'))
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-xl">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-xl">{{ session('error') }}</div>
@endif

<div class="mb-5">
    <label for="course-live-search" class="sr-only">Search courses</label>
    <div class="relative max-w-xl">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
            <i class="fas fa-search text-sm" aria-hidden="true"></i>
        </span>
        <input type="search"
               id="course-live-search"
               name="q"
               value="{{ request('q') }}"
               autocomplete="off"
               placeholder="Search by name, code, or class…"
               class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-sm text-gray-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        <button type="button"
                id="course-search-clear"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 {{ request()->filled('q') ? '' : 'hidden' }}"
                aria-label="Clear search">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>
    <p id="course-search-status" class="mt-2 text-xs text-gray-500 min-h-[1rem]"></p>
</div>

<div id="courses-list">
    @include('admin.partials.courses-list', ['courses' => $courses])
</div>

@push('scripts')
<script>
(function () {
    var input = document.getElementById('course-live-search');
    var clearBtn = document.getElementById('course-search-clear');
    var listHost = document.getElementById('courses-list');
    var statusEl = document.getElementById('course-search-status');
    if (!input || !listHost) return;

    var debounceTimer = null;
    var activeController = null;
    var listUrl = @json(route('dashboard.courses.index'));

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text || '';
    }

    function toggleClear() {
        if (!clearBtn) return;
        clearBtn.classList.toggle('hidden', input.value.trim() === '');
    }

    function updateUrl(q) {
        var url = new URL(window.location.href);
        if (q) {
            url.searchParams.set('q', q);
        } else {
            url.searchParams.delete('q');
        }
        url.searchParams.delete('page');
        window.history.replaceState({}, '', url);
    }

    function applyLocalFilter(q) {
        var needle = q.trim().toLowerCase();
        var rows = listHost.querySelectorAll('.course-row');
        var visible = 0;
        rows.forEach(function (row) {
            var blob = row.getAttribute('data-search') || '';
            var show = !needle || blob.indexOf(needle) !== -1;
            row.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        var empty = listHost.querySelector('#courses-empty-state');
        if (empty) {
            empty.classList.toggle('hidden', visible > 0 || needle === '');
        }
        if (needle && rows.length) {
            setStatus(visible === 1 ? '1 course on this page' : visible + ' courses on this page');
        } else if (!needle) {
            setStatus('');
        }
    }

    function fetchResults(q) {
        if (activeController) activeController.abort();
        activeController = new AbortController();
        setStatus('Searching…');

        var url = listUrl + (q ? ('?q=' + encodeURIComponent(q)) : '');
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            signal: activeController.signal
        }).then(function (res) {
            if (!res.ok) throw new Error('Search failed');
            return res.text();
        }).then(function (html) {
            listHost.innerHTML = html;
            updateUrl(q);
            toggleClear();
            if (q) {
                var count = listHost.querySelectorAll('.course-row').length;
                setStatus(count === 1 ? '1 course found' : count + ' courses found');
            } else {
                setStatus('');
            }
        }).catch(function (err) {
            if (err && err.name === 'AbortError') return;
            applyLocalFilter(q);
            setStatus('Showing matches on this page only');
        });
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        var q = input.value;
        toggleClear();
        debounceTimer = setTimeout(function () {
            if (q.trim().length === 0) {
                fetchResults('');
                return;
            }
            if (q.trim().length < 2) {
                applyLocalFilter(q);
                setStatus('Type at least 2 characters to search all courses');
                return;
            }
            fetchResults(q.trim());
        }, 280);
    }

    input.addEventListener('input', scheduleSearch);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            toggleClear();
            fetchResults('');
            input.focus();
        });
    }

    toggleClear();
})();
</script>
@endpush
@endsection
