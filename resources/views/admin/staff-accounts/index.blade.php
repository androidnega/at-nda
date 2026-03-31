@extends('layouts.admin')

@section('title', 'User management')

@section('content')
<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary">User management</h1>
            <p class="text-gray-500 text-sm mt-1">Administrator accounts for this dashboard (staff login). <strong>Lecturers</strong> are added under <a href="{{ route('dashboard.lecturers.index') }}" class="text-primary hover:underline">Lecturers</a> without passwords.</p>
        </div>
        <a href="{{ route('dashboard.staff-accounts.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90 shrink-0">
            <i class="fas fa-user-plus"></i>
            Add administrator
        </a>
    </div>
</div>

@if (session('success'))
    <div class="mb-6 p-4 bg-green-50 text-green-800 rounded-xl border border-green-100 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-6 p-4 bg-red-50 text-red-800 rounded-xl border border-red-100 flex items-center gap-2">
        <i class="fas fa-exclamation-circle text-red-600"></i>
        {{ session('error') }}
    </div>
@endif

<section class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-user-shield text-primary"></i>
            Administrators
        </h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[400px]">
            <thead class="bg-white border-b border-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($admins as $u)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $u->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $u->email }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $u->username ?? '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('dashboard.staff-accounts.admins.edit', $u) }}" class="text-primary hover:underline text-sm font-medium">Edit</a>
                        <form action="{{ route('dashboard.staff-accounts.admins.destroy', $u) }}" method="POST" class="inline ml-3" onsubmit="return confirm('Remove this administrator account?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Remove</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No administrator accounts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
