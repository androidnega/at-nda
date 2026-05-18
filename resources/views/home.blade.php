@extends('layouts.home')

@section('title', 'Sign in — '.config('app.name'))

@section('content')
<x-auth-signin-layout hero-loading="eager">
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Student access</h2>
        <p class="text-[13px] text-gray-600 mt-1 leading-snug">Use your official student ID to open your {{ config('app.name') }} account.</p>
    </div>

    @if (session('success'))
        <div class="mb-3 p-2.5 bg-emerald-50 text-emerald-800 rounded-lg text-sm border border-emerald-100">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">{{ session('error') }}</div>
    @endif
    @if (session('info'))
        <div class="mb-3 p-2.5 bg-sky-50 text-sky-800 rounded-lg text-sm border border-sky-100">{{ session('info') }}</div>
    @endif

    <form method="POST" action="{{ route('student.lookup') }}" class="space-y-3">
        @csrf
        <div>
            <label for="index_number" class="block text-xs font-medium text-gray-700 mb-1.5">Student ID</label>
            <input type="text" id="index_number" name="index_number" required autofocus
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition uppercase"
                placeholder="e.g. BC/ITD/24/001" style="text-transform: uppercase;" value="{{ old('index_number') }}"
                autocomplete="username">
        </div>
        @error('index_number')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="w-full bg-gradient-to-r from-gray-900 to-gray-800 text-white py-2.5 rounded-xl text-sm font-semibold hover:from-gray-800 hover:to-gray-700 transition">
            Continue
        </button>
    </form>
</x-auth-signin-layout>
@endsection
