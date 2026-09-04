@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white dark:bg-gray-900">
    <div class="flex h-screen w-full flex-col justify-center items-center p-6">
        <div class="max-w-2xl w-full text-center">
            <!-- Success Icon -->
            <div class="mb-8">
                <div class="w-24 h-24 bg-success-500 rounded-full flex items-center justify-center mx-auto">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>

            <!-- Success Message -->
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                Registration Successful! 🎉
            </h1>

            @if(session('partner'))
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                    Welcome to <strong>{{ session('partner') }}</strong>!
                </p>
            @endif

            <div class="bg-brand-50 dark:bg-brand-500/10 border-l-4 border-brand-500 p-6 mb-8 text-left rounded-lg">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Next Step: Login to Your Account
                </h2>
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    Your account has been successfully created! Click the button below to log in and start tracking your fitness journey.
                </p>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Your Login Credentials:</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-3 rounded">
                            <span class="text-gray-600 dark:text-gray-400">Email:</span>
                            <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ session('email') }}</span>
                        </div>
                        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-3 rounded">
                            <span class="text-gray-600 dark:text-gray-400">Password:</span>
                            <span class="text-gray-900 dark:text-white">The password you just created</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Button -->
            <div class="mb-8">
                <a href="{{ config('app.webapp_url', 'http://localhost:5173') }}/login" 
                   class="inline-flex items-center justify-center px-8 py-4 bg-brand-500 hover:bg-brand-600 text-white text-lg font-semibold rounded-lg transition-colors shadow-theme-md">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Login to Fit Nation
                </a>
            </div>

            <!-- Features List -->
            <div class="text-left bg-gray-50 dark:bg-gray-800 rounded-lg p-6 mb-8">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">What you can do:</h3>
                <ul class="space-y-3 text-gray-700 dark:text-gray-300">
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-success-500 mr-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Track your workouts and exercises in real-time</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-success-500 mr-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Monitor your progress with detailed analytics</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-success-500 mr-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Access custom workout templates from your gym</span>
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-success-500 mr-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Browse exercise library with detailed instructions</span>
                    </li>
                </ul>
            </div>

            <!-- Help Text -->
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Need help? Contact your gym or email <a href="mailto:support@fitnation.com" class="text-brand-500 hover:text-brand-600 underline">support@fitnation.com</a>
            </p>
        </div>
    </div>
</div>
@endsection

