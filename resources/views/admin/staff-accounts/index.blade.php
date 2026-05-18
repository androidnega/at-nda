@extends('layouts.admin')

@section('title', 'User management')

@section('content')
<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary">User management</h1>
            <p class="text-gray-500 text-sm mt-1">Administrator and lecturer accounts for staff login at <strong>/admin</strong>.</p>
        </div>
        <a href="{{ route('dashboard.staff-accounts.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-medium hover:bg-primary/90 shrink-0">
            <i class="fas fa-user-plus"></i>
            Add staff account
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

<section class="bg-white rounded-xl border border-gray-100 overflow-hidden mt-6">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-chalkboard-teacher text-primary"></i>
            Lecturer Login Accounts
        </h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[400px]">
            <thead class="bg-white border-b border-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">First login status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Presence stats</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($lecturersWithLogin as $l)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $l->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ ($hasUsernameColumn ?? false) ? ($l->username ?: '—') : '—' }}</td>
                    <td class="px-4 py-3 text-sm">
                        @if(($hasMustChangeColumn ?? false) && $l->must_change_password)
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">Must change password</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-medium">Updated</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-700">
                        @php $summary = $lecturerPresenceSummary[$l->id] ?? ['total_present' => 0, 'by_course' => collect()]; @endphp
                        <p class="font-semibold text-gray-900">{{ $summary['total_present'] }} present session(s)</p>
                        @foreach(($summary['by_course'] ?? collect())->take(3) as $row)
                            <p class="mt-1">{{ $row['course_name'] }}{{ $row['course_code'] ? ' (' . $row['course_code'] . ')' : '' }} · {{ $row['class_name'] ?: 'No class' }} · {{ $row['present_sessions'] }}</p>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <form action="{{ route('dashboard.staff-accounts.lecturers.reset-password', $l) }}" method="POST" class="inline" onsubmit="return confirm('Generate new temporary password for this lecturer?')">
                            @csrf
                            <button type="submit" class="text-primary hover:underline text-sm font-medium">Reset password</button>
                        </form>
                        <form action="{{ route('dashboard.staff-accounts.lecturers.destroy', $l) }}" method="POST" class="inline ml-3" onsubmit="return confirm('Remove this lecturer login account?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Remove login</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No lecturer login accounts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
