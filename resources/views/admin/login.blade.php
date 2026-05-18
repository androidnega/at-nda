<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="app-viewport-lock">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <title>Staff Login - {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }, colors: { primary: '#e11d48' } } } }</script>
    @include('partials.minimal-ui')
    @include('partials.viewport-lock-styles')
</head>
<body class="bg-slate-50 h-[100dvh] max-h-[100dvh] overflow-hidden flex items-center justify-center p-4 font-sans">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl border border-gray-100 p-8">
            <div class="text-center mb-8">
                <span class="w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center text-primary mx-auto mb-4">
                    <i class="fas fa-user-shield text-2xl"></i>
                </span>
                <h1 class="text-2xl font-bold text-gray-800">Admin Login</h1>
                <p class="text-gray-500 text-sm mt-1">{{ config('app.name') }} · Admin & Lecturer</p>
            </div>
            @if (session('error'))
                <div class="mb-4 p-3 bg-red-50 text-red-800 rounded-xl text-sm">{{ session('error') }}</div>
            @endif
            <form action="{{ route('admin.login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="identifier" class="block text-sm font-medium text-gray-700 mb-2">Email or Username</label>
                    <input type="text" id="identifier" name="identifier" value="{{ old('identifier') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary"
                        placeholder="admin or admin@admin.com">
                    @error('identifier')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" required
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
                    @error('password')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full bg-primary text-white py-3 rounded-xl font-medium hover:bg-primary/90">
                    Sign In
                </button>
            </form>
            <p class="text-center text-gray-500 text-sm mt-6">
                <a href="{{ route('home') }}" class="text-primary hover:underline">← Back to Home</a>
            </p>
        </div>
    </div>
</body>
</html>
