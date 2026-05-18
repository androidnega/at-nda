@extends('layouts.admin')

@section('title', 'Students')

@section('content')
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">
    <div>
        <h1 class="text-2xl font-bold text-primary">All students</h1>
        <p class="text-gray-600 text-sm mt-1">{{ number_format($totalStudents ?? $students->total()) }} in the system · search, filter by class, add or import</p>
    </div>
</div>

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
            <p class="text-xs text-gray-500">Upload Excel/CSV with student rows.</p>
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

<div class="mb-4 flex flex-wrap gap-2 items-center">
    <input type="text" id="student-search" placeholder="Search index or name..." value="{{ request('search') }}"
        class="flex-1 min-w-[180px] max-w-xs border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20">
    <select id="student-class" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 min-w-[120px]">
        <option value="">All classes</option>
        @foreach($classes ?? [] as $c)
        <option value="{{ $c->id }}" {{ request('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
    </select>
    <select id="student-program" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 min-w-[80px]">
        <option value="">All</option>
        <option value="ITN" {{ request('program') == 'ITN' ? 'selected' : '' }}>ITN</option>
        <option value="ITS" {{ request('program') == 'ITS' ? 'selected' : '' }}>ITS</option>
        <option value="ITD" {{ request('program') == 'ITD' ? 'selected' : '' }}>ITD</option>
    </select>
</div>

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="p-2 border-b border-gray-100 bg-gray-50/50">
        <p class="text-xs text-gray-500">Excel: index_number, first_name, middle_name (optional), last_name. <a href="{{ asset('sample_students.xlsx') }}" download class="text-primary hover:underline">Sample</a></p>
    </div>
    <div id="students-container" class="max-h-[calc(100vh-320px)] overflow-y-auto overflow-x-auto">
        <table class="w-full min-w-[720px]">
            <thead class="bg-gray-50 border-b border-gray-100 sticky top-0 z-10">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide w-14">#</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide w-14"><span class="sr-only">Photo</span></th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Index</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide hidden sm:table-cell">Class</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide hidden md:table-cell">Program</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Rep</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide w-16"><span class="sr-only">Open</span></th>
                </tr>
            </thead>
            <tbody id="students-tbody" class="divide-y divide-gray-100">
                @forelse($students as $s)
                @include('partials.student-table-row', [
                    'student' => $s,
                    'serial' => $students->firstItem() + $loop->index,
                    'detailUrl' => route('dashboard.students.show', $s),
                    'showClassColumn' => true,
                ])
                @empty
                <tr id="students-empty-initial">
                    <td colspan="8" class="px-4 py-12 text-center text-gray-500 text-sm">No students yet. Add one above or import an Excel file.</td>
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
    var programSelect = document.getElementById('student-program');

    var page = {{ $students->hasMorePages() ? $students->currentPage() + 1 : 1 }};
    var hasMore = {{ $students->hasMorePages() ? 'true' : 'false' }};
    var loadingData = false;
    var searchTimeout = null;
    var nextSerial = {{ $students->isEmpty() ? 1 : ($students->lastItem() + 1) }};

    function esc(t) {
        return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getProgramClass(programKey) {
        if (!programKey) return 'bg-gray-100 text-gray-600';
        if (programKey === 'ITN') return 'bg-blue-100 text-blue-700';
        if (programKey === 'ITS') return 'bg-emerald-100 text-emerald-700';
        if (programKey === 'ITD') return 'bg-violet-100 text-violet-700';
        return 'bg-gray-100 text-gray-600';
    }

    function renderRow(s, serial) {
        var repCell = s.is_rep
            ? '<span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Rep</span>'
            : '<span class="text-gray-400 text-sm">—</span>';
        var url = '{{ route("dashboard.students.show", ["student" => "__ID__"]) }}'.replace('__ID__', s.id);
        var prog = esc(s.program_label || '—');
        var photoCell = s.profile_image_url
            ? '<img src="' + esc(s.profile_image_url) + '" alt="" class="h-9 w-9 rounded-full object-cover border border-gray-200 bg-gray-50" loading="lazy">'
            : '<span class="inline-flex h-9 w-9 rounded-full bg-primary/10 text-primary items-center justify-center text-xs font-semibold">' + esc(s.avatar_initials || '—') + '</span>';
        return '<tr class="hover:bg-gray-50/50 even:bg-gray-50/40">' +
            '<td class="px-4 py-3 text-sm text-gray-500 tabular-nums align-top w-14">' + serial + '</td>' +
            '<td class="px-4 py-3 align-top w-12">' + photoCell + '</td>' +
            '<td class="px-4 py-3 font-mono text-sm text-gray-800 whitespace-nowrap align-top">' + esc(s.index_number) + '</td>' +
            '<td class="px-4 py-3 min-w-0 align-top"><span class="font-medium text-gray-900">' + esc(s.display_name || '—') + '</span></td>' +
            '<td class="px-4 py-3 hidden sm:table-cell align-top text-sm text-gray-600">' + esc(s.class_name || '—') + '</td>' +
            '<td class="px-4 py-3 hidden md:table-cell align-top"><span class="inline-block px-2 py-0.5 rounded text-xs font-medium ' + getProgramClass(s.program_key) + '">' + prog + '</span></td>' +
            '<td class="px-4 py-3 align-top">' + repCell + '</td>' +
            '<td class="px-4 py-3 text-right align-top w-16">' +
                '<a href="' + url + '" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-600 hover:bg-primary/10 hover:border-primary/30 hover:text-primary transition-colors" title="View">' +
                '<i class="fas fa-chevron-right text-sm"></i></a></td>' +
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
            class_id: classSelect?.value || '',
            program: programSelect?.value || ''
        });

        fetch('{{ route("dashboard.students.index") }}?' + params, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(function(data) {
            if (data.students && data.students.length) {
                data.students.forEach(function(s) {
                    s.program_label = s.program_label || (s.index_number && s.index_number.includes('ITN') ? 'ITN' : (s.index_number && s.index_number.includes('ITS') ? 'ITS' : (s.index_number && s.index_number.includes('ITD') ? 'ITD' : '—')));
                    tbody.insertAdjacentHTML('beforeend', renderRow(s, nextSerial));
                    nextSerial += 1;
                });
            }
            if (reset && (!data.students || !data.students.length)) {
                var initialEmpty = document.getElementById('students-empty-initial');
                if (initialEmpty) initialEmpty.remove();
                empty.classList.remove('hidden');
            }
            hasMore = data.has_more || false;
            page = data.next_page || page + 1;
        })
        .catch(function() { if (reset) empty.classList.remove('hidden'); })
        .finally(function() { loadingData = false; loading.classList.add('hidden'); });
    }

    searchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { fetchStudents(true); }, 300);
    });
    classSelect?.addEventListener('change', function() { fetchStudents(true); });
    programSelect?.addEventListener('change', function() { fetchStudents(true); });

    container?.addEventListener('scroll', function() {
        if (container.scrollTop + container.clientHeight >= container.scrollHeight - 100) {
            fetchStudents(false);
        }
    });
})();
</script>
@endpush
