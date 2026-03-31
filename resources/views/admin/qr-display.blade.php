@extends('layouts.admin')

@section('title', 'QR Code - ' . $session->course->course_name)

@section('content')
<div class="max-w-md mx-auto text-center">
    <h1 class="text-xl font-bold mb-2">{{ $session->course->course_name }}</h1>
    <p class="text-gray-500 text-sm mb-4">Scan with student app ({{ $session->mode }} mode)</p>
    <div class="relative inline-block">
        <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-blue-500/30 via-indigo-500/25 to-blue-500/30 qr-pulse-ring" aria-hidden="true"></div>
        <div class="relative bg-white p-6 rounded-xl shadow-sm border border-gray-100 inline-block">
            <img id="admin-qr-img" src="{{ $qrUrl }}" alt="QR Code" class="w-64 h-64 sm:w-80 sm:h-80">
        </div>
    </div>
    <style>
        @keyframes qr-pulse-ring {
            0%, 100% { opacity: 0.55; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }
        .qr-pulse-ring { animation: qr-pulse-ring 2.2s ease-in-out infinite; }
    </style>
    <p class="text-gray-500 text-xs mt-4">Expires: {{ $session->expires_at?->format('M d, H:i') ?? 'No expiry' }}</p>
    <div class="mt-5 flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="{{ route('dashboard.sessions.qr-download', $session) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-5 py-3 text-sm font-semibold shadow-md hover:bg-slate-800 transition-colors">
            <i class="fas fa-download" aria-hidden="true"></i>
            Download PNG (print)
        </a>
        <a href="{{ session('student_id') ? route('dashboard.dashboard') : route('dashboard.courses.index') }}" class="inline-block text-primary hover:underline text-sm">← Back to {{ session('student_id') ? 'Dashboard' : 'Courses' }}</a>
    </div>
</div>
@endsection
