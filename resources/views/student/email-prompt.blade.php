@extends('layouts.home')

@section('title', 'Add a recovery email — '.config('app.name'))

@section('content')
<x-auth-signin-layout>
    @include('partials.atenda-brand', ['compact' => false, 'brandMb' => 'mb-4'])

    <div class="rounded-xl bg-emerald-50/70 border border-emerald-100 px-3.5 py-3 mb-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Welcome back, {{ $student->first_name ?: $student->index_number }}</h2>
        <p class="text-[13px] text-emerald-900/90 mt-1 leading-snug">
            Add an email so we can send you a reset code if you ever forget your password.
            This is <strong>optional</strong> — you can skip and continue.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-3 p-2.5 bg-red-50 text-red-800 rounded-lg text-sm border border-red-100">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('student.email-prompt.submit') }}" class="space-y-3">
        @csrf
        <div>
            <label for="email" class="block text-xs font-medium text-gray-700 mb-1.5">Recovery email</label>
            <input type="email" id="email" name="email"
                value="{{ old('email') }}"
                autofocus inputmode="email" autocomplete="email"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500/25 focus:border-emerald-400 outline-none transition"
                placeholder="you@example.com">
            <p class="mt-1.5 text-[11px] text-gray-500">
                We'll only use this for password resets — never for marketing or attendance reminders.
            </p>
        </div>

        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 text-sm font-semibold transition">
            <i class="fas fa-envelope text-xs"></i>
            Save my email
        </button>

        <button type="submit" name="skip" value="1"
                class="w-full inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:text-gray-800 hover:bg-gray-50 py-2.5 text-sm font-medium transition">
            Skip for now
        </button>

        <p class="text-[11px] text-gray-400 text-center pt-1">
            You can update this later from your profile.
        </p>
    </form>
</x-auth-signin-layout>
@endsection
