@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
    <div class="flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto w-full max-w-md pt-5 sm:py-10">
                <a href="/" class="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to home
                </a>
            </div>

            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                    @if(isset($invitation) && isset($partner))
                        <!-- Gym Branding Header -->
                        <div class="mb-6 text-center">
                            @if($partner->identity && $partner->identity->logo_url)
                                <img src="{{ $partner->identity->logo_url }}" alt="{{ $partner->name }}" class="w-20 h-20 mx-auto mb-4 rounded object-cover">
                            @endif
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                Welcome to {{ $partner->name }}!
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Complete your registration to start tracking your fitness journey
                            </p>
                        </div>
                    @else
                        <div class="mb-5 sm:mb-8">
                            <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                                Sign Up
                            </h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Enter your details to create an account
                            </p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}">
                    @csrf

                    @if(isset($invitation))
                        <input type="hidden" name="invitation_token" value="{{ $invitation->token }}">
                    @endif

                    <div class="space-y-5">
                        <!-- Name -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Name<span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Enter your name"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required autofocus />
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Email<span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="email" name="email" 
                                value="{{ old('email', isset($invitation) ? $invitation->email : '') }}" 
                                {{ isset($invitation) ? 'readonly' : '' }}
                                placeholder="Enter your email"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required />
                            @error('email')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @if(isset($invitation))
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This email is pre-filled from your invitation</p>
                            @endif
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Password<span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required autocomplete="new-password" />
                            @error('password')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Confirm Password<span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm your password"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required autocomplete="new-password" />
                            @error('password_confirmation')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" 
                            class="w-full h-11 rounded-lg bg-gray-900 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 dark:focus:ring-white">
                            Register
                        </button>

                        <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                            Already have an account?
                            <a href="{{ route('login') }}" class="font-medium text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300">
                                Sign in
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Side - Image/Pattern (Optional) -->
        <div class="hidden lg:flex lg:w-1/2 lg:items-center lg:justify-center lg:bg-gradient-to-br lg:from-blue-500 lg:to-blue-700">
            <div class="text-center text-white p-12">
                <h2 class="text-3xl font-bold mb-4">Start Your Fitness Journey</h2>
                <p class="text-lg opacity-90">Track your workouts, monitor progress, and achieve your fitness goals.</p>
            </div>
        </div>
    </div>
</div>
@endsection

