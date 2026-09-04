<x-guest-layout>
    <div>
        <div class="mb-5 sm:mb-8">
            <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">
                {{ __('Reset Password') }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Enter your email and choose a new password.') }}
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-success-50 p-4 text-sm text-success-600 dark:bg-success-500/15 dark:text-success-500">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="space-y-5">
                <!-- Email Address -->
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        {{ __('Email') }}<span class="text-error-500">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $request->email) }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('email') border-error-500 @enderror"
                        placeholder="{{ __('Email') }}"
                    />
                    @error('email')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        {{ __('Password') }}<span class="text-error-500">*</span>
                    </label>
                    <div x-data="{ showPassword: false }" class="relative">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="{{ __('Enter your password') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('password') border-error-500 @enderror"
                        />
                        <span
                            @click="showPassword = !showPassword"
                            class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer text-gray-500 dark:text-gray-400"
                        >
                            <svg x-show="!showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.707 4.293a1 1 0 00-1.414 1.414l12 12a1 1 0 001.414-1.414l-12-12zm-2 3.414a1 1 0 012 0v7a1 1 0 102 0v-7a1 1 0 012 0v7a3 3 0 11-6 0v-7z" fill="currentColor" />
                            </svg>
                            <svg x-show="showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 4a4 4 0 00-4 4v1a1 1 0 01-2 0V8a6 6 0 1112 0v1a1 1 0 11-2 0V8a4 4 0 00-4-4z" fill="currentColor" />
                            </svg>
                        </span>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        {{ __('Confirm Password') }}<span class="text-error-500">*</span>
                    </label>
                    <div x-data="{ showPasswordConfirmation: false }" class="relative">
                        <input
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="{{ __('Confirm your password') }}"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('password_confirmation') border-error-500 @enderror"
                        />
                        <span
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                            class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer text-gray-500 dark:text-gray-400"
                        >
                            <svg x-show="!showPasswordConfirmation" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.707 4.293a1 1 0 00-1.414 1.414l12 12a1 1 0 001.414-1.414l-12-12zm-2 3.414a1 1 0 012 0v7a1 1 0 102 0v-7a1 1 0 012 0v7a3 3 0 11-6 0v-7z" fill="currentColor" />
                            </svg>
                            <svg x-show="showPasswordConfirmation" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 4a4 4 0 00-4 4v1a1 1 0 01-2 0V8a6 6 0 1112 0v1a1 1 0 11-2 0V8a4 4 0 00-4-4z" fill="currentColor" />
                            </svg>
                        </span>
                    </div>
                    @error('password_confirmation')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button
                        type="submit"
                        class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition"
                    >
                        {{ __('Reset Password') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-guest-layout>
