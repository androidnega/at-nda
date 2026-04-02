@extends('layouts.classrep')

@section('title', 'Close session')

@section('header')
    <span class="text-sm font-semibold text-gray-800 truncate">Close session</span>
@endsection

@section('content')
<div class="max-w-lg mx-auto w-full">
    <div class="rounded-xl bg-white border border-gray-200 p-6 sm:p-8">
        <h1 class="text-lg font-bold text-gray-900">Close this session?</h1>
        <p class="text-sm text-gray-600 mt-2">
            {{ $session->course?->course_name ?? 'Session' }} — closing will stop students from marking attendance for this run.
        </p>
        <form action="{{ route('dashboard.live-sessions.close', $session) }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-3 rounded-lg font-semibold bg-rose-600 text-white hover:bg-rose-700">
                    Yes, close session
                </button>
                <a href="{{ route('dashboard.session') }}" class="flex-1 inline-flex justify-center items-center px-4 py-3 rounded-lg font-semibold border border-gray-200 text-gray-800 hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
