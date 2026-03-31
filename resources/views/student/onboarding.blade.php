@php
    $lay = $layout ?? 'student';
    $missingFields = $missingFields ?? ['first_name', 'last_name', 'phone_number', 'profile_image'];
@endphp
@extends($lay === 'courserep' ? 'layouts.courserep' : 'layouts.student')

@section('title', 'Welcome')

@if($lay === 'courserep')
@section('header')
    <span class="text-sm font-semibold text-gray-800 truncate">Welcome — tell us about you</span>
@endsection
@else
@section('breadcrumb')
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm text-slate-500">
        <span class="font-semibold text-slate-800 truncate">Welcome</span>
    </nav>
@endsection
@endif

@section('content')
<div class="w-full {{ ($lay ?? '') === 'courserep' ? 'max-w-6xl mx-auto' : 'max-w-lg sm:max-w-xl' }}">
    <div class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-7">
        <h1 class="text-xl font-bold text-slate-900">Welcome</h1>
        <p class="text-slate-500 text-sm mt-1">
            @if(in_array('profile_image', $missingFields, true))
                Add your details, mobile number, and a clear photo of yourself to continue.
            @elseif(count($missingFields) >= 3)
                Enter your name and mobile number to continue.
            @else
                Please complete the following {{ count($missingFields) === 1 ? 'field' : 'fields' }} to continue.
            @endif
        </p>

        @if (session('error'))
            <div class="mt-4 p-4 bg-red-50 text-red-800 rounded-xl text-sm border border-red-100">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('student.onboarding.post') }}" class="mt-6 space-y-4" enctype="multipart/form-data">
            @csrf
            @if(in_array('first_name', $missingFields, true))
            <div>
                <label for="first_name" class="block text-sm font-medium text-slate-700 mb-2">First name</label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $student->first_name) }}" required autocomplete="given-name"
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('first_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if(in_array('last_name', $missingFields, true))
            <div>
                <label for="last_name" class="block text-sm font-medium text-slate-700 mb-2">Last name</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $student->last_name) }}" required autocomplete="family-name"
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('last_name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if(in_array('phone_number', $missingFields, true))
            <div>
                <label for="phone_number" class="block text-sm font-medium text-slate-700 mb-2">Mobile phone</label>
                <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number', $student->phone_number) }}" required inputmode="tel" autocomplete="tel" minlength="10"
                    placeholder="e.g. 0244123456"
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                @error('phone_number')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @endif
            @if(in_array('profile_image', $missingFields, true))
                @include('partials.student-profile-camera', ['prefix' => 'onboarding', 'required' => true, 'label' => 'Profile photo'])
                @error('profile_photo')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            @endif
            <button type="submit" class="w-full bg-amber-700 text-white py-3 rounded-xl font-semibold hover:bg-amber-800 transition">
                Continue
            </button>
        </form>
    </div>
</div>
@endsection
