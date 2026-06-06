@extends('layouts.admin')

@section('title', 'Students')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">
    <div>
        @php
            $isLecturerStudents = session()->has('lecturer_id') && !session()->has('admin_id');
            $filterClass = collect($classes ?? [])->firstWhere('id', (int) request('class_id'));
        @endphp
        <h1 class="text-2xl font-bold text-primary">{{ $isLecturerStudents ? 'My students' : 'All students' }}</h1>
        <p class="text-gray-600 text-sm mt-1">
            @if($filterClass)
                {{ $filterClass->name }} · {{ number_format($students->total()) }} students
            @else
                {{ number_format($totalStudents ?? $students->total()) }} {{ $isLecturerStudents ? 'in your classes' : 'in the system' }}
            @endif
            · search and filter · add or import roster
        </p>
        @if($isLecturerStudents)
        <a href="{{ route('dashboard.dashboard') }}" class="inline-flex items-center gap-1.5 mt-2 text-sm text-primary hover:underline"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
        @endif
    </div>
</div>

@php $isLecturerStudents = session()->has('lecturer_id') && !session()->has('admin_id'); @endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <form action="{{ route('dashboard.students.store') }}" method="POST" class="bg-white rounded-xl border border-gray-100 p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
        @csrf
        <div class="md:col-span-2">
            <p class="text-sm font-semibold text-gray-800">Add Student Individually</p>
            <p class="text-xs text-gray-500">Student will be treated as new and complete onboarding on first access.</p>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Index Number</label>
            <input type="text" name="index_number" value="{{ old('index_number') }}" required
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20">
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Class</label>
            <select name="class_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20">
                <option value="">Select class</option>
                @foreach($classes ?? [] as $c)
                    <option value="{{ $c->id }}" {{ (string) old('class_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">First Name (optional)</label>
            <input type="text" name="first_name" value="{{ old('first_name') }}"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20">
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Last Name (optional)</label>
            <input type="text" name="last_name" value="{{ old('last_name') }}"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 text-sm font-medium">Add Student</button>
        </div>
    </form>

    <form action="{{ route('dashboard.students.import') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-100 p-4 flex flex-col gap-3">
        @csrf
        <div>
            <p class="text-sm font-semibold text-gray-800">Bulk Import Students</p>
            <p class="text-xs text-gray-500">
                Upload Excel/CSV with index numbers.
                @if($isLecturerStudents)
                Include a <strong>class</strong> column for your assigned classes, or use <a href="{{ route('dashboard.my-classes.index') }}" class="text-primary hover:underline">per-class roster upload</a>.
                @endif
            </p>
        </div>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
            class="text-sm file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700">
        <div>
            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-800 text-sm font-medium">Import</button>
        </div>
    </form>
</div>

@if (session('success'))
    <div class="mb-4 p-3 bg-emerald-50 text-emerald-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 p-3 bg-red-50 text-red-800 rounded-lg text-sm">
        <p class="font-semibold mb-1">Please fix these fields:</p>
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Wrap the filters in a real <form> so Enter / blur falls back to a
     plain GET request. That way, even if the inline AJAX layer breaks for
     some reason (CSP, missing JSON header, etc.), super-admins can still
     find a student by index number — the search becomes part of the URL
     and the server returns the matching paginated list. --}}
<form id="student-filter-form" method="GET" action="{{ route('dashboard.students.index') }}"
      class="mb-4 flex flex-wrap gap-2 items-center">
    <div class="relative flex-1 min-w-[200px] max-w-md">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="search" id="student-search" name="search" value="{{ request('search') }}"
            placeholder="Search across {{ number_format($totalStudents ?? $students->total()) }} students by index or name…"
            autocomplete="off"
            class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-sm focus:ring-2 focus:ring-primary/20">
    </div>
    <select id="student-class" name="class_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 min-w-[120px]">
        <option value="">All classes</option>
        @foreach($classes ?? [] as $c)
        <option value="{{ $c->id }}" {{ request('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="inline-flex items-center gap-1.5 bg-primary text-white px-3.5 py-2 rounded-lg text-sm font-medium hover:bg-primary/90">
        <i class="fas fa-magnifying-glass text-[11px]"></i> Search
    </button>
    @if(request('search') || request('class_id'))
        <a href="{{ route('dashboard.students.index') }}" class="text-xs text-gray-500 hover:text-gray-700 py-2 inline-flex items-center gap-1">
            <i class="fas fa-times text-[10px]"></i> Clear
        </a>
    @endif
</form>

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="p-2 border-b border-gray-100 bg-gray-50/50">
        <p class="text-xs text-gray-500">Click any student to see their details. Excel template: <a href="{{ asset('sample_students.xlsx') }}" download class="text-primary hover:underline">sample_students.xlsx</a></p>
    </div>
    <div id="students-container" class="max-h-[calc(100vh-320px)] overflow-y-auto overflow-x-auto">
        <table class="w-full min-w-[560px]">
            <thead class="bg-gray-50 border-b border-gray-100 sticky top-0 z-10">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide w-14">#</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide w-14"><span class="sr-only">Photo</span></th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Index</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide hidden sm:table-cell">Class</th>
                </tr>
            </thead>
            <tbody id="students-tbody" class="divide-y divide-gray-100">
                @forelse($students as $s)
                    @php
                        $serial = $students->firstItem() + $loop->index;
                        $detailUrl = route('dashboard.students.show', $s);
                        $isRep = $s->isClassRep();
                    @endphp
                    <tr
                        data-student-row
                        data-student-id="{{ $s->id }}"
                        data-display-name="{{ e($s->getDisplayName() ?: '') }}"
                        data-index="{{ e($s->index_number ?? '') }}"
                        data-class="{{ e($s->schoolClass?->name ?? '') }}"
                        data-program-label="{{ e($s->getProgramLabel() ?: '') }}"
                        data-photo-url="{{ e($s->profileImageUrl() ?: '') }}"
                        data-initials="{{ e($s->avatarInitials() ?: '') }}"
                        data-is-rep="{{ $isRep ? '1' : '0' }}"
                        data-detail-url="{{ $detailUrl }}"
                        class="cursor-pointer hover:bg-primary/5 transition-colors">
                        <td class="px-4 py-3 text-sm text-gray-500 tabular-nums w-14">{{ $serial }}</td>
                        <td class="px-4 py-3 w-12">
                            @if($s->profile_image)
                                <img src="{{ $s->profileImageUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover border border-gray-200 bg-gray-50" loading="lazy">
                            @else
                                <span class="inline-flex h-9 w-9 rounded-full bg-primary/10 text-primary items-center justify-center text-xs font-semibold">{{ $s->avatarInitials() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-sm text-gray-800 whitespace-nowrap">{{ $s->index_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <span class="font-medium">{{ $s->getDisplayName() ?: '—' }}</span>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell text-sm text-gray-600">{{ $s->schoolClass?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr id="students-empty-initial">
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500 text-sm">No students yet. Add one above or import an Excel file.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div id="students-loading" class="hidden p-4 text-center text-gray-500 text-sm border-t border-gray-100">Loading...</div>
        <div id="students-empty" class="hidden p-8 text-center text-gray-500 text-sm border-t border-gray-100">No students found.</div>
    </div>
    @if($students->hasPages())
    <div class="p-4 border-t border-gray-100">
        {{ $students->links() }}
    </div>
    @endif
</div>

{{-- Student detail modal --}}
<div id="student-modal" class="fixed inset-0 z-50 hidden items-center justify-center px-4">
    <div class="absolute inset-0 bg-black/40" data-modal-close></div>
    <div class="relative bg-white rounded-2xl shadow-xl border border-gray-100 w-full max-w-md overflow-hidden">
        <button type="button" data-modal-close
                class="absolute top-3 right-3 h-8 w-8 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 flex items-center justify-center">
            <i class="fas fa-xmark"></i>
        </button>
        <div class="p-6 text-center border-b border-gray-100">
            <div id="modal-photo-wrap" class="mx-auto mb-3 h-20 w-20 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl font-bold overflow-hidden">
                <span id="modal-initials">—</span>
            </div>
            <p id="modal-name" class="text-lg font-semibold text-gray-900">—</p>
            <p id="modal-index" class="text-sm font-mono text-gray-500 mt-0.5">—</p>
        </div>
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex items-center justify-between gap-3 px-6 py-3">
                <dt class="text-gray-500">Class</dt>
                <dd id="modal-class" class="text-gray-900 font-medium text-right">—</dd>
            </div>
        </dl>
        <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
            <a id="modal-open-full" href="#" class="rounded-lg bg-primary text-white px-4 py-2 text-sm font-semibold hover:bg-primary/90 inline-flex items-center gap-1.5">
                <i class="fas fa-arrow-up-right-from-square text-xs"></i> Open full profile
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var tbody = document.getElementById('students-tbody');
    var container = document.getElementById('students-container');
    var loading = document.getElementById('students-loading');
    var empty = document.getElementById('students-empty');
    var searchInput = document.getElementById('student-search');
    var classSelect = document.getElementById('student-class');

    var page = {{ $students->hasMorePages() ? $students->currentPage() + 1 : 1 }};
    var hasMore = {{ $students->hasMorePages() ? 'true' : 'false' }};
    var loadingData = false;
    var searchTimeout = null;
    var nextSerial = {{ $students->isEmpty() ? 1 : ($students->lastItem() + 1) }};
    var detailUrlTpl = '{{ route("dashboard.students.show", ["student" => "__ID__"]) }}';

    function esc(t) {
        return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderRow(s, serial) {
        var url = detailUrlTpl.replace('__ID__', s.id);
        var photoCell = s.profile_image_url
            ? '<img src="' + esc(s.profile_image_url) + '" alt="" class="h-9 w-9 rounded-full object-cover border border-gray-200 bg-gray-50" loading="lazy">'
            : '<span class="inline-flex h-9 w-9 rounded-full bg-primary/10 text-primary items-center justify-center text-xs font-semibold">' + esc(s.avatar_initials || '—') + '</span>';
        var attrs = ''
            + ' data-student-row'
            + ' data-student-id="' + esc(s.id) + '"'
            + ' data-display-name="' + esc(s.display_name || '') + '"'
            + ' data-index="' + esc(s.index_number || '') + '"'
            + ' data-class="' + esc(s.class_name || '') + '"'
            + ' data-program-label="' + esc(s.program_label || '') + '"'
            + ' data-photo-url="' + esc(s.profile_image_url || '') + '"'
            + ' data-initials="' + esc(s.avatar_initials || '') + '"'
            + ' data-is-rep="' + (s.is_rep ? '1' : '0') + '"'
            + ' data-detail-url="' + esc(url) + '"';
        return '<tr' + attrs + ' class="cursor-pointer hover:bg-primary/5 transition-colors">' +
            '<td class="px-4 py-3 text-sm text-gray-500 tabular-nums w-14">' + serial + '</td>' +
            '<td class="px-4 py-3 w-12">' + photoCell + '</td>' +
            '<td class="px-4 py-3 font-mono text-sm text-gray-800 whitespace-nowrap">' + esc(s.index_number) + '</td>' +
            '<td class="px-4 py-3 text-sm text-gray-900"><span class="font-medium">' + esc(s.display_name || '—') + '</span></td>' +
            '<td class="px-4 py-3 hidden sm:table-cell text-sm text-gray-600">' + esc(s.class_name || '—') + '</td>' +
            '</tr>';
    }

    function fetchStudents(reset) {
        if (loadingData) return;
        if (reset) {
            page = 1;
            hasMore = true;
            tbody.innerHTML = '';
            nextSerial = 1;
        }
        if (!hasMore && !reset) return;

        loadingData = true;
        loading.classList.remove('hidden');
        empty.classList.add('hidden');

        var params = new URLSearchParams({
            page: page,
            search: searchInput?.value || '',
            class_id: classSelect?.value || ''
        });

        fetch('{{ route("dashboard.students.index") }}?' + params, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (r) {
            // Session expired? Server redirected the AJAX request to a
            // non-JSON page (login). Don't silently show "no students" —
            // do a real form submit so the user lands on login or
            // gets the server-rendered results back.
            var ct = (r.headers && r.headers.get('Content-Type')) || '';
            if (!r.ok || ct.indexOf('application/json') === -1) {
                if (reset && form && typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    throw new Error('fallback_form_submit');
                }
                throw new Error('non_json_response');
            }
            return r.json();
        })
        .then(function(data) {
            if (data.students && data.students.length) {
                data.students.forEach(function(s) {
                    s.program_label = s.program_label || (s.index_number && s.index_number.includes('ITN') ? 'ITN' : (s.index_number && s.index_number.includes('ITS') ? 'ITS' : (s.index_number && s.index_number.includes('ITD') ? 'ITD' : '—')));
                    tbody.insertAdjacentHTML('beforeend', renderRow(s, nextSerial));
                    nextSerial += 1;
                });
                empty.classList.add('hidden');
            }
            if (reset && (!data.students || !data.students.length)) {
                var initialEmpty = document.getElementById('students-empty-initial');
                if (initialEmpty) initialEmpty.remove();
                var term = (searchInput?.value || '').trim();
                empty.textContent = term
                    ? 'No students match "' + term + '". Try a different name or index number — search runs across every class.'
                    : 'No students found.';
                empty.classList.remove('hidden');
            }
            hasMore = data.has_more || false;
            page = data.next_page || page + 1;
        })
        .catch(function (err) {
            if (err && err.message === 'fallback_form_submit') return;
            if (reset) {
                empty.textContent = 'Could not search right now. Tap "Search" to refresh, or check your connection.';
                empty.classList.remove('hidden');
            }
        })
        .finally(function() { loadingData = false; loading.classList.add('hidden'); });
    }

    var form = document.getElementById('student-filter-form');

    searchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { fetchStudents(true); }, 300);
    });
    classSelect?.addEventListener('change', function() { fetchStudents(true); });

    container?.addEventListener('scroll', function() {
        if (container.scrollTop + container.clientHeight >= container.scrollHeight - 100) {
            fetchStudents(false);
        }
    });

    // Pressing Enter submits the real form (GET) so the search lives in the URL.
    // The keypress that produced this is allowed to propagate to the form, so
    // we don't need to call form.submit() ourselves. The AJAX path above
    // continues to work for "type-as-you-go" results without a reload.

    // If the user landed on the page with a `search=` or `class_id=` query
    // string, the server already rendered the matching rows. Do NOT re-fetch
    // and replace them — that hides results when the JSON branch is down.

    // --- Modal handling ---
    var modal = document.getElementById('student-modal');
    function openModalFromRow(row) {
        if (!modal || !row) return;
        document.getElementById('modal-name').textContent = row.dataset.displayName || '—';
        document.getElementById('modal-index').textContent = row.dataset.index || '—';
        document.getElementById('modal-class').textContent = row.dataset.class || '—';
        var photoWrap = document.getElementById('modal-photo-wrap');
        var photoUrl = row.dataset.photoUrl || '';
        if (photoUrl) {
            photoWrap.innerHTML = '<img src="' + photoUrl + '" alt="" class="h-full w-full object-cover">';
        } else {
            photoWrap.innerHTML = '<span id="modal-initials">' + (row.dataset.initials || '—') + '</span>';
        }
        document.getElementById('modal-open-full').setAttribute('href', row.dataset.detailUrl || '#');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
    document.addEventListener('click', function (e) {
        var row = e.target.closest && e.target.closest('[data-student-row]');
        if (row && !e.target.closest('a, button, form')) {
            openModalFromRow(row);
            return;
        }
        if (e.target.matches && e.target.matches('[data-modal-close]')) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
@endpush
