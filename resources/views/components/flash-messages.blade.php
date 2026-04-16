{{--
    Global flash + validation summary (included in layouts.app, layouts.guest, layouts.fullscreen-layout).

    From controllers:
    - return redirect()->...->with('success', 'Message');
    - return redirect()->...->with('error', 'Message');
    - Optional: ->with('alert_title', 'Custom error heading') (defaults to "Something went wrong")
    - return redirect()->...->with('warning', 'Message');
    - return redirect()->...->with('info', 'Message');

    Validation errors ($errors) render automatically after a failed form request.

    Override titles: <x-flash-messages success-title="Saved" validation-title="Fix the form" />
--}}
@props([
    'successTitle' => 'Success',
    'errorTitle' => null,
    'warningTitle' => 'Please note',
    'infoTitle' => 'Information',
    'validationTitle' => 'Please check the form',
])

@php
    $errorHeading = $errorTitle ?? session('alert_title', 'Something went wrong');
    $hasValidation = isset($errors) && $errors->any();
    $hasFlash = session()->has('success')
        || session()->has('error')
        || session()->has('warning')
        || session()->has('info')
        || $hasValidation;
@endphp

@if ($hasFlash)
    <div {{ $attributes->merge(['class' => 'mb-6 space-y-4']) }}>
        @if (session('success'))
            <x-ui.alert variant="success" :title="$successTitle">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ session('success') }}</p>
            </x-ui.alert>
        @endif

        @if (session('error'))
            <x-ui.alert variant="error" :title="$errorHeading">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ session('error') }}</p>
            </x-ui.alert>
        @endif

        @if (session('warning'))
            <x-ui.alert variant="warning" :title="$warningTitle">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ session('warning') }}</p>
            </x-ui.alert>
        @endif

        @if (session('info'))
            <x-ui.alert variant="info" :title="$infoTitle">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ session('info') }}</p>
            </x-ui.alert>
        @endif

        @if ($hasValidation)
            <x-ui.alert variant="error" :title="$validationTitle">
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                @if ($errors->has('user_id') && filled(old('user_id', request('user_id'))))
                    <a
                        href="{{ route('users.subscriptions.index', ['user' => old('user_id', request('user_id'))]) }}"
                        class="mt-3 inline-block text-sm font-medium text-red-700 underline decoration-red-300 underline-offset-2 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200"
                    >
                        View this member's subscriptions
                    </a>
                @endif
            </x-ui.alert>
        @endif
    </div>
@endif
