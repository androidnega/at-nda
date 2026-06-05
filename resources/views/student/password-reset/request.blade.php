@extends('layouts.home')

@section('title', 'Forgot password — '.config('app.name'))

@section('content')
<x-auth-signin-layout>
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2.5 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Forgot password</h2>
        <p class="text-[13px] text-gray-600 mt-1 leading-snug">Enter your student index number and we'll email a 6-digit reset code.</p>
    </div>

    @if (! $featureAvailable)
        <div class="mb-3 p-2.5 bg-amber-50 text-amber-800 rounded-lg text-sm border border-amber-100">
            Password reset by email isn't set up on this server yet. Ask your class rep or admin to add your email and configure the mailer.
        </div>
    @endif

    @if (session('success'))
        <div class="mb-3 p-2.5 bg-emerald-50 text-emerald-800 rounded-lg text-sm border border-emerald-100">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('student.password.request.send') }}" class="space-y-3">
        @csrf
        <div>
            <label for="index_number" class="block text-xs font-medium text-gray-700 mb-1.5">Student Index Number</label>
            <input type="text" id="index_number" name="index_number"
                value="{{ old('index_number', $indexNumber ?? '') }}"
                required autofocus inputmode="text" autocapitalize="characters" autocomplete="username"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono uppercase focus:ring-2 focus:ring-rose-500/25 focus:border-rose-400 outline-none transition"
                placeholder="e.g. BC/ITS/24/047">
        </div>
        @error('index_number')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror

        <button type="submit" class="w-full bg-gradient-to-r from-gray-900 to-gray-800 text-white py-2.5 rounded-xl text-sm font-semibold hover:from-gray-800 hover:to-gray-700 transition">
            <i class="fas fa-paper-plane mr-2 text-xs"></i> Send reset code
        </button>
    </form>

    <div class="mt-4 flex items-center justify-between gap-3 text-xs text-gray-500">
        <a href="{{ route('home') }}" class="hover:text-gray-800 transition">
            <i class="fas fa-arrow-left mr-1 text-[10px]"></i> Back to sign in
        </a>
        <a href="{{ route('student.password.verify.form') }}" class="hover:text-gray-800 transition">
            I already have a code <i class="fas fa-arrow-right ml-1 text-[10px]"></i>
        </a>
    </div>
</x-auth-signin-layout>
@endsection
