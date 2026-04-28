@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white dark:bg-gray-900">
    <div class="flex h-screen w-full flex-col justify-center items-center p-6">
        <div class="max-w-2xl w-full text-center">
            <!-- Success Icon -->
            <div class="mb-8">
                <div class="w-24 h-24 bg-green-500 rounded-full flex items-center justify-center mx-auto">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>

            <!-- Message -->
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                Email Verified!
            </h1>

            <p class="text-xl text-gray-600 dark:text-gray-400 mb-8">
                Your email address has been successfully verified.
            </p>

            <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-6 mb-8 text-left rounded-lg">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Next Step
                </h2>
                <p class="text-gray-700 dark:text-gray-300">
                    Return to the <strong>Fit Nation</strong> app on your device and log in with your email and password to start your fitness journey.
                </p>
            </div>

            <!-- Help Text -->
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Need help? Contact your gym or email <a href="mailto:support@fitnation.com" class="text-blue-500 hover:text-blue-600 underline">support@fitnation.com</a>
            </p>
        </div>
    </div>
</div>
@endsection
