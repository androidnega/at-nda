@extends('layouts.app')

@section('title', 'Attendance Marked - ' . $course->course_name)

@section('content')
<div class="min-h-[60vh] flex flex-col items-center justify-center px-4">
    <div class="text-center max-w-sm">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full border border-green-200 bg-white text-green-600 mb-6 animate-success-pop">
            <i class="fa-solid fa-circle-check text-5xl leading-none" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Attendance Marked</h1>
        <p class="text-gray-600 mb-6">{{ $course->course_name }}{{ $course->course_code ? ' (' . $course->course_code . ')' : '' }}</p>
        <p id="success-redirect-note" class="text-sm text-slate-500">Returning to your dashboard in 3 seconds...</p>
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
<script>
setTimeout(function() {
    window.location.href = '{{ route('dashboard.dashboard') }}';
}, 3000);
</script>
@endsection
