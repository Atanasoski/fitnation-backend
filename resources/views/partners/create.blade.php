@extends('layouts.app')

@section('title', 'Create Partner')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create New Partner" :items="[['label' => 'Partners', 'url' => route('partners.index')]]" />

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
            <div class="mb-2 text-sm font-semibold text-error-800 dark:text-error-400">
                There were some errors with your submission:
            </div>
            <ul class="list-inside list-disc space-y-1 text-sm text-error-700 dark:text-error-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-common.component-card title="Partner Information" desc="Fill in the details to create a new partner">
        <form action="{{ route('partners.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column - Partner Details -->
                <div class="space-y-5">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Partner Details</h3>

                    <!-- Partner Name -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Partner Name <span class="text-error-500">*</span>
                        </label>
                        <input type="text"
                            name="name"
                            id="name"
                            value="{{ old('name') }}"
                            required
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @enderror" />
                        @error('name')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Slug -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Slug <span class="text-error-500">*</span>
                        </label>
                        <input type="text"
                            name="slug"
                            id="slug"
                            value="{{ old('slug') }}"
                            readonly
                            required
                            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:placeholder:text-white/30 cursor-not-allowed" />
                        @error('slug')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Auto-generated from partner name</p>
                    </div>

                    <!-- Domain -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Domain
                        </label>
                        <input type="text"
                            name="domain"
                            id="domain"
                            value="{{ old('domain') }}"
                            placeholder="partner.yourdomain.com"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('domain') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @enderror" />
                        @error('domain')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Optional subdomain for this partner</p>
                    </div>

                    <!-- Active Checkbox -->
                    <div x-data="{ checkboxToggle: {{ old('is_active', true) ? 'true' : 'false' }} }">
                        <input type="hidden" name="is_active" value="0" />
                        <label for="is_active"
                            class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                            <div class="relative">
                                <input type="checkbox"
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    class="sr-only"
                                    @change="checkboxToggle = !checkboxToggle"
                                    {{ old('is_active', true) ? 'checked' : '' }} />
                                <div :class="checkboxToggle ? 'border-brand-500 bg-brand-500' :
                                    'bg-transparent border-gray-300 dark:border-gray-700'"
                                    class="hover:border-brand-500 dark:hover:border-brand-500 mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px]">
                                    <span :class="checkboxToggle ? '' : 'opacity-0'">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white"
                                                stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            Active
                        </label>
                    </div>
                </div>

                <!-- Right Column - Branding -->
                <div class="space-y-5">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Branding</h3>

                    <!-- Primary Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Primary Color <span class="text-error-500">*</span>
                        </label>
                        <input type="color"
                            name="primary_color"
                            id="primary_color"
                            value="{{ old('primary_color', '#ff6b35') }}"
                            required
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('primary_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Secondary Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Secondary Color <span class="text-error-500">*</span>
                        </label>
                        <input type="color"
                            name="secondary_color"
                            id="secondary_color"
                            value="{{ old('secondary_color', '#4ecdc4') }}"
                            required
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('secondary_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Background Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Background Color
                        </label>
                        <input type="color"
                            name="background_color"
                            id="background_color"
                            value="{{ old('background_color', '#ffffff') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('background_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Card Background Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Card Background Color
                        </label>
                        <input type="color"
                            name="card_background_color"
                            id="card_background_color"
                            value="{{ old('card_background_color', '#ffffff') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('card_background_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text Primary Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Text Primary Color
                        </label>
                        <input type="color"
                            name="text_primary_color"
                            id="text_primary_color"
                            value="{{ old('text_primary_color', '#000000') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('text_primary_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text Secondary Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Text Secondary Color
                        </label>
                        <input type="color"
                            name="text_secondary_color"
                            id="text_secondary_color"
                            value="{{ old('text_secondary_color', '#6b7280') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('text_secondary_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text On Primary Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Text On Primary Color
                        </label>
                        <input type="color"
                            name="text_on_primary_color"
                            id="text_on_primary_color"
                            value="{{ old('text_on_primary_color', '#ffffff') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('text_on_primary_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Success Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Success Color
                        </label>
                        <input type="color"
                            name="success_color"
                            id="success_color"
                            value="{{ old('success_color', '#10dc60') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('success_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Warning Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Warning Color
                        </label>
                        <input type="color"
                            name="warning_color"
                            id="warning_color"
                            value="{{ old('warning_color', '#ffce00') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('warning_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Danger Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Danger Color
                        </label>
                        <input type="color"
                            name="danger_color"
                            id="danger_color"
                            value="{{ old('danger_color', '#f04141') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('danger_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Accent Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Accent Color
                        </label>
                        <input type="color"
                            name="accent_color"
                            id="accent_color"
                            value="{{ old('accent_color', '#8ac34a') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('accent_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Border Color -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Border Color
                        </label>
                        <input type="color"
                            name="border_color"
                            id="border_color"
                            value="{{ old('border_color', '#dee2e6') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('border_color')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Dark Mode Colors Section -->
            <div class="mt-8">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-5">Dark Mode Colors</h3>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Primary Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Primary Color (Dark)
                        </label>
                        <input type="color"
                            name="primary_color_dark"
                            id="primary_color_dark"
                            value="{{ old('primary_color_dark', '#fa812d') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('primary_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Secondary Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Secondary Color (Dark)
                        </label>
                        <input type="color"
                            name="secondary_color_dark"
                            id="secondary_color_dark"
                            value="{{ old('secondary_color_dark', '#292a2c') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('secondary_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Background Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Background Color (Dark)
                        </label>
                        <input type="color"
                            name="background_color_dark"
                            id="background_color_dark"
                            value="{{ old('background_color_dark', '#121212') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('background_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Card Background Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Card Background Color (Dark)
                        </label>
                        <input type="color"
                            name="card_background_color_dark"
                            id="card_background_color_dark"
                            value="{{ old('card_background_color_dark', '#1e1e1e') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('card_background_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text Primary Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Text Primary Color (Dark)
                        </label>
                        <input type="color"
                            name="text_primary_color_dark"
                            id="text_primary_color_dark"
                            value="{{ old('text_primary_color_dark', '#ffffff') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('text_primary_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text Secondary Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Text Secondary Color (Dark)
                        </label>
                        <input type="color"
                            name="text_secondary_color_dark"
                            id="text_secondary_color_dark"
                            value="{{ old('text_secondary_color_dark', '#b0b0b0') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('text_secondary_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Text On Primary Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Text On Primary Color (Dark)
                        </label>
                        <input type="color"
                            name="text_on_primary_color_dark"
                            id="text_on_primary_color_dark"
                            value="{{ old('text_on_primary_color_dark', '#ffffff') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('text_on_primary_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Success Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Success Color (Dark)
                        </label>
                        <input type="color"
                            name="success_color_dark"
                            id="success_color_dark"
                            value="{{ old('success_color_dark', '#4ade80') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('success_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Warning Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Warning Color (Dark)
                        </label>
                        <input type="color"
                            name="warning_color_dark"
                            id="warning_color_dark"
                            value="{{ old('warning_color_dark', '#fff94f') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('warning_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Danger Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Danger Color (Dark)
                        </label>
                        <input type="color"
                            name="danger_color_dark"
                            id="danger_color_dark"
                            value="{{ old('danger_color_dark', '#ff6b6b') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('danger_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Accent Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Accent Color (Dark)
                        </label>
                        <input type="color"
                            name="accent_color_dark"
                            id="accent_color_dark"
                            value="{{ old('accent_color_dark', '#fff94f') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('accent_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Border Color Dark -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Border Color (Dark)
                        </label>
                        <input type="color"
                            name="border_color_dark"
                            id="border_color_dark"
                            value="{{ old('border_color_dark', '#3a3a3a') }}"
                            class="h-12 w-full cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700" />
                        @error('border_color_dark')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Logo and Other Assets Section -->
            <div class="mt-8">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-5">Assets</h3>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Logo -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Logo
                        </label>
                        <input type="file"
                            name="logo"
                            id="logo"
                            accept="image/*"
                            class="focus:border-ring-brand-300 shadow-theme-xs focus:file:ring-brand-300 h-11 w-full overflow-hidden rounded-lg border border-gray-300 bg-transparent text-sm text-gray-500 transition-colors file:mr-5 file:border-collapse file:cursor-pointer file:rounded-l-lg file:border-0 file:border-r file:border-solid file:border-gray-200 file:bg-gray-50 file:py-3 file:pr-3 file:pl-3.5 file:text-sm file:text-gray-700 placeholder:text-gray-400 hover:file:bg-gray-100 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:text-white/90 dark:file:border-gray-800 dark:file:bg-white/[0.03] dark:file:text-gray-400 dark:placeholder:text-gray-400" />
                        @error('logo')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload partner logo (PNG, JPG, SVG)</p>
                    </div>

                    <!-- Background Pattern -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Background Pattern
                        </label>
                        <input type="file"
                            name="background_pattern"
                            id="background_pattern"
                            accept="image/*"
                            class="focus:border-ring-brand-300 shadow-theme-xs focus:file:ring-brand-300 h-11 w-full overflow-hidden rounded-lg border border-gray-300 bg-transparent text-sm text-gray-500 transition-colors file:mr-5 file:border-collapse file:cursor-pointer file:rounded-l-lg file:border-0 file:border-r file:border-solid file:border-gray-200 file:bg-gray-50 file:py-3 file:pr-3 file:pl-3.5 file:text-sm file:text-gray-700 placeholder:text-gray-400 hover:file:bg-gray-100 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:text-white/90 dark:file:border-gray-800 dark:file:bg-white/[0.03] dark:file:text-gray-400 dark:placeholder:text-gray-400" />
                        @error('background_pattern')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload background pattern image (PNG, JPG, SVG)</p>
                    </div>

                    <!-- Font Family -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Font Family
                        </label>
                        <input type="text"
                            name="font_family"
                            id="font_family"
                            value="{{ old('font_family', 'Inter') }}"
                            placeholder="Inter, Poppins, Roboto"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('font_family') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @enderror" />
                        @error('font_family')
                            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                <a href="{{ route('partners.index') }}">
                    <x-ui.button variant="outline" size="md">
                        Cancel
                    </x-ui.button>
                </a>
                <x-ui.button variant="primary" size="md" type="submit">
                    Create Partner
                </x-ui.button>
            </div>
        </form>
    </x-common.component-card>

    @push('scripts')
        <script>
            // Auto-generate slug from name
            document.getElementById('name').addEventListener('input', function(e) {
                const slug = e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)/g, '');
                document.getElementById('slug').value = slug;
            });
        </script>
    @endpush
@endsection
