<x-guest-layout>
    <div>
        <!-- Error Messages -->
        @if(session('error'))
            <div class="mb-6 rounded-lg bg-error-50 border-l-4 border-error-500 p-4 dark:bg-error-900/20">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-error-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-error-800 dark:text-error-200 mb-1">Invitation Issue</h3>
                        <p class="text-sm text-error-700 dark:text-error-300">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @else
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
                        Name<span class="text-error-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Enter your name"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required autofocus />
                    @error('name')
                        <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Email<span class="text-error-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" 
                        value="{{ old('email', isset($invitation) ? $invitation->email : '') }}" 
                        {{ isset($invitation) ? 'readonly' : '' }}
                        placeholder="Enter your email"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required />
                    @error('email')
                        <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                    @if(isset($invitation))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This email is pre-filled from your invitation</p>
                    @endif
                </div>

                <!-- Password -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Password<span class="text-error-500">*</span>
                    </label>
                    <input type="password" id="password" name="password" placeholder="Enter your password"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required autocomplete="new-password" />
                    @error('password')
                        <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Confirm Password<span class="text-error-500">*</span>
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm your password"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" required autocomplete="new-password" />
                    @error('password_confirmation')
                        <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" 
                    class="w-full h-11 rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white shadow-theme-xs transition-colors hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    Register
                </button>

                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400 dark:hover:text-brand-300">
                        Sign in
                    </a>
                </p>
            </div>
        </form>
        @endif
    </div>
</x-guest-layout>
