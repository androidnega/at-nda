@extends('layouts.app')

@section('title', 'Attendance Marked - ' . $course->course_name)

@section('content')
<div class="min-h-[60vh] flex flex-col items-center justify-center px-4">
    <div class="text-center max-w-sm">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-green-100 mb-6 animate-success-pop">
            <span class="text-5xl">✅</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Attendance Marked</h1>
        <p class="text-gray-600 mb-6">{{ $course->course_name }}{{ $course->course_code ? ' (' . $course->course_code . ')' : '' }}</p>
        <div class="flex flex-wrap gap-3 justify-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-blue-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Back to Home
            </a>
            <a href="{{ route('dashboard.dashboard') }}" class="inline-flex items-center gap-2 bg-gray-100 text-gray-800 px-6 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">
                My Dashboard
            </a>
        </div>
    </div>
</div>
<style>
@keyframes success-pop {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}
.animate-success-pop { animation: success-pop 0.5s ease-out forwards; }
</style>
@endsection
