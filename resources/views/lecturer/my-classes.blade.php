@extends('layouts.admin')

@section('title', 'My classes')

@section('content')
<div class="mb-6">
    <a href="{{ route('dashboard.dashboard') }}" class="text-gray-500 hover:text-gray-700 text-sm mb-2 inline-flex items-center gap-1">
        <i class="fas fa-arrow-left"></i> Dashboard
    </a>
    <h1 class="text-2xl font-bold text-primary">My classes</h1>
    <p class="text-gray-500 text-sm mt-1">Upload rosters and manage students per class</p>
</div>

@if($classes->isEmpty())
<div class="bg-white rounded-xl border border-gray-100 p-12 text-center text-gray-500">
    No classes assigned yet. Ask an administrator to link you to classes.
</div>
@else
<div class="grid gap-4 sm:grid-cols-2">
    @foreach($classes as $class)
    <a href="{{ route('dashboard.classes.show', $class) }}"
        class="block bg-white rounded-xl border border-gray-100 p-5 hover:border-primary/40 hover:shadow-sm transition">
        <div class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-layer-group"></i>
            </span>
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-900">{{ $class->name }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Level {{ $class->level ?? '—' }}
                    @if($class->faculty) · {{ $class->faculty->name }}@endif
                </p>
                <p class="text-xs text-sky-700 font-medium mt-2">
                    {{ $class->students_count }} {{ \Illuminate\Support\Str::plural('student', $class->students_count) }}
                    · Upload roster
                </p>
            </div>
        </div>
    </a>
    @endforeach
</div>
@endif
@endsection
