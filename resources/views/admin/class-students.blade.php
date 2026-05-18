@extends('layouts.admin')

@section('title', $schoolClass->name . ' - Students')

@section('content')
<div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <a href="{{ route('dashboard.classes.index') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Classes
        </a>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary">{{ $schoolClass->name }}</h1>
        <p class="text-gray-500 text-sm mt-1">{{ $schoolClass->faculty?->name ?? '—' }} · {{ $schoolClass->department?->name ?? '—' }} · Level {{ $schoolClass->level ?? '—' }}</p>
    </div>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 p-4 sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Add one student</h2>
        <form method="POST" action="{{ route('dashboard.classes.students.store', $schoolClass) }}" class="space-y-3">
            @csrf
            <div>
                <label for="index_number" class="block text-xs font-medium text-gray-700 mb-1">Index number</label>
                <input type="text" id="index_number" name="index_number" value="{{ old('index_number') }}" required
                    placeholder="e.g. UEB123456"
                    class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm uppercase focus:ring-2 focus:ring-primary focus:border-primary">
                @error('index_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label for="first_name" class="block text-xs font-medium text-gray-700 mb-1">First name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}"
                        class="w-full border-2 border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="middle_name" class="block text-xs font-medium text-gray-700 mb-1">Middle</label>
                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}"
                        class="w-full border-2 border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="last_name" class="block text-xs font-medium text-gray-700 mb-1">Last name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}"
                        class="w-full border-2 border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>
            <p class="text-xs text-gray-500">Existing index → name and class are <strong>overwritten</strong>.</p>
            <button type="submit" class="inline-flex items-center gap-2 bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-900">
                <i class="fas fa-user-plus"></i> Save student
            </button>
        </form>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 p-4 sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Bulk upload</h2>
        <p class="text-sm text-gray-600 mb-4">CSV or Excel with flexible headers. Re-uploading an index <strong>overwrites</strong> the row and assigns this class.</p>
        <form action="{{ route('dashboard.classes.students.import', $schoolClass) }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv,.txt" required
                class="flex-1 text-sm file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary">
            <button type="submit" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-primary/90 shrink-0">
                <i class="fas fa-upload"></i> Upload
            </button>
        </form>
    </div>
</div>

@if(!$students->isEmpty())
<form method="GET" action="{{ route('dashboard.classes.show', $schoolClass) }}" class="mb-6">
    <div class="flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or index..."
            class="flex-1 min-w-[200px] border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary">
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90">
            <i class="fas fa-search mr-1"></i> Search
        </button>
        @if(request('search'))
        <a href="{{ route('dashboard.classes.show', $schoolClass) }}" class="text-gray-500 hover:text-gray-700 px-4 py-2.5">Clear</a>
        @endif
    </div>
</form>
@endif

@if($students->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 p-12 text-center">
        <span class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400 mx-auto mb-4">
            <i class="fas fa-user-graduate text-3xl"></i>
        </span>
        <p class="text-gray-600 font-medium">No students in this class</p>
        <p class="text-gray-500 text-sm mt-1">Add a student above or upload a file</p>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px]">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide w-14">#</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide w-14"><span class="sr-only">Photo</span></th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Index</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide hidden md:table-cell">Program</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Rep</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide w-16"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($students as $student)
                    @include('partials.student-table-row', [
                        'student' => $student,
                        'serial' => $students->firstItem() + $loop->index,
                        'detailUrl' => route('dashboard.students.show', ['student' => $student, 'from_class' => $schoolClass->id]),
                        'contextClassId' => $schoolClass->id,
                    ])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6">
        {{ $students->links() }}
    </div>
@endif
@endsection
