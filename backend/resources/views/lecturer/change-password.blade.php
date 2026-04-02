<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">
    <title>Update Password - {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-['Inter']">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl border border-gray-100 p-8">
            <h1 class="text-2xl font-bold text-gray-800">Set your new password</h1>
            <p class="text-gray-500 text-sm mt-1">Welcome, {{ $lecturer->name }}. Please update your temporary password before continuing.</p>

            @if ($errors->any())
                <div class="mt-4 p-3 bg-red-50 text-red-800 rounded-xl text-sm">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('lecturer.password.change.post') }}" method="POST" class="space-y-4 mt-5">
                @csrf
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">New password</label>
                    <input type="password" id="password" name="password" required minlength="6"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <button type="submit" class="w-full bg-primary text-white py-3 rounded-xl font-medium hover:bg-primary/90">Update and continue</button>
            </form>
        </div>
    </div>
</body>
</html>
