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
    <form action="{{ route('dashboard.classes.students.import', $schoolClass) }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-2">
        @csrf
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
            class="text-sm file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary">
        <button type="submit" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90 shrink-0">
            <i class="fas fa-upload"></i>
            Import Students
        </button>
    </form>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

<div class="bg-white rounded-xl border border-gray-100 p-4 sm:p-6 mb-6">
    <p class="text-sm text-gray-600">Excel format: <strong>index_number</strong>, <strong>first_name</strong>, <strong>middle_name</strong> (optional), <strong>last_name</strong>. First row = headers.</p>
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
        <p class="text-gray-500 text-sm mt-1">Upload an Excel file to add students</p>
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
                        'detailUrl' => route('dashboard.students.show', $student),
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
