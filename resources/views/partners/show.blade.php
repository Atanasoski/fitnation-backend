@extends('layouts.app')

@section('title', $partner->name)

@section('content')

<div class="mb-6">
    <a href="{{ route('partners.index') }}" class="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        BACK TO PARTNERS
    </a>
</div>

<!-- Header Card -->
<div class="mb-6 rounded-2xl border border-gray-200 dark:border-gray-800" style="background: linear-gradient(135deg, {{ $colors['primary'] }} 0%, {{ $colors['secondary'] }} 100%);">
    <div class="p-6">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
            <!-- Logo/Avatar -->
            <div class="flex-shrink-0">
                @if($partner->identity?->logo)
                    <div class="w-24 h-24 rounded-2xl border-4 border-white dark:border-gray-800 shadow-lg overflow-hidden bg-white dark:bg-gray-800 p-2">
                        <img src="{{ asset($partner->identity->logo) }}" alt="{{ $partner->name }}" class="w-full h-full object-contain">
                    </div>
                @else
                    <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-orange-400 via-orange-500 to-brand-400 flex items-center justify-center text-white text-3xl font-bold shadow-lg">
                        {{ strtoupper(substr($partner->name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <!-- Partner Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <h2 class="text-3xl font-bold text-white mb-2">{{ $partner->name }}</h2>
                        <div class="flex items-center gap-3 flex-wrap">
                            <code class="text-sm bg-white/20 backdrop-blur-sm px-3 py-1 rounded-lg text-white">{{ $partner->slug }}</code>
                            @if($partner->domain)
                                <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-white/20 backdrop-blur-sm text-white">
                                    {{ $partner->domain }}
                                </span>
                            @endif
                            @if($partner->is_active)
                                <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-white/20 backdrop-blur-sm text-white">
                                    Active
                                </span>
                            @else
                                <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-white/10 backdrop-blur-sm text-white/80">
                                    Inactive
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <a href="{{ route('partners.edit', $partner) }}" class="inline-flex items-center justify-center rounded-lg border border-white/30 bg-white/20 backdrop-blur-sm px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-white/30">
                            Edit
                        </a>
                        <form action="{{ route('partners.destroy', $partner) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this partner?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-white/30 bg-white/20 backdrop-blur-sm px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-white/30">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
                <div class="flex items-center gap-6 text-sm text-white/90">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Created {{ $partner->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Updated {{ $partner->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Users</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-white/90">{{ $usersCount }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-brand-50 dark:bg-brand-500/15 flex items-center justify-center">
                <svg class="w-6 h-6 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Status</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    @if($partner->is_active)
                        <span class="text-success-600 dark:text-success-500">Active</span>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">Inactive</span>
                    @endif
                </p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-success-50 dark:bg-success-500/15 flex items-center justify-center">
                <svg class="w-6 h-6 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Branding</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    {{ $partner->identity ? 'Configured' : 'Not Set' }}
                </p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-orange-50 dark:bg-orange-500/15 flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">User Since</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $partner->created_at->format('M Y') }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-orange-50 dark:bg-orange-500/15 flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Active This Week -->
    <div class="bg-success-500 rounded-2xl shadow-sm p-6 text-white">
        <div class="flex items-center">
            <div class="shrink-0">
                <svg class="w-12 h-12 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-3xl font-bold">{{ $activeMembersThisWeek }}</h3>
                <p class="text-sm opacity-90">Active This Week</p>
                <p class="text-xs opacity-75 mt-1">{{ $totalMembers > 0 ? round(($activeMembersThisWeek / $totalMembers) * 100) : 0 }}% of users</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Branding Section -->
    <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Brand Identity</h3>
        </div>
        <div class="p-6">
            @if($partner->identity)
                <div class="space-y-6">
                    <!-- Color Palette -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Color Palette</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($colorPalette as $color)
                                @if($color['value'])
                                    <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div style="width: 40px; height: 40px; border-radius: 8px; background-color: {{ $color['value'] }}; border: 2px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $color['name'] }}</p>
                                                <code class="text-xs font-mono text-gray-700 dark:text-gray-300 break-all">{{ $color['value'] }}</code>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <!-- Color Palette Dark -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Color Palette Dark</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($darkColorPalette as $color)
                                @if($color['value'])
                                    <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div style="width: 40px; height: 40px; border-radius: 8px; background-color: {{ $color['value'] }}; border: 2px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $color['name'] }}</p>
                                                <code class="text-xs font-mono text-gray-700 dark:text-gray-300 break-all">{{ $color['value'] }}</code>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <!-- Logo -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Logo</h4>
                        @if($partner->identity->logo)
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <div class="flex-shrink-0">
                                    <img src="{{ asset($partner->identity->logo) }}" alt="{{ $partner->name }} Logo" class="w-20 h-20 object-contain rounded-lg border-2 border-gray-200 dark:border-gray-700 p-2 bg-white dark:bg-gray-900 shadow-sm">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">File Path</p>
                                    <code class="text-xs text-gray-600 dark:text-gray-400 block mb-3 break-all">{{ $partner->identity->logo }}</code>
                                    <a href="{{ asset($partner->identity->logo) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-brand-600 dark:text-brand-400 hover:underline">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                        View full size
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="p-8 text-center rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <svg class="w-12 h-12 text-gray-400 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No logo uploaded</p>
                            </div>
                        @endif
                    </div>

                    <!-- Background Pattern -->
                    @if($partner->identity->background_pattern)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Background Pattern</h4>
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <div class="flex-shrink-0">
                                    <img src="{{ asset($partner->identity->background_pattern) }}" alt="Background Pattern" class="w-20 h-20 object-contain rounded-lg border-2 border-gray-200 dark:border-gray-700 p-2 bg-white dark:bg-gray-900 shadow-sm">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">File Path</p>
                                    <code class="text-xs text-gray-600 dark:text-gray-400 block mb-3 break-all">{{ $partner->identity->background_pattern }}</code>
                                    <a href="{{ asset($partner->identity->background_pattern) }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-brand-600 dark:text-brand-400 hover:underline">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                        View full size
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Font Family -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-4">Typography</h4>
                        @if($partner->identity->font_family)
                            <div class="p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-gray-50 to-white dark:from-gray-800 dark:to-gray-900">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Font Family</p>
                                <p style="font-family: {{ $partner->identity->font_family }}; font-size: 24px;" class="text-gray-800 dark:text-white/90 font-semibold">
                                    {{ $partner->identity->font_family }}
                                </p>
                                <p style="font-family: {{ $partner->identity->font_family }}; font-size: 16px;" class="text-gray-700 dark:text-gray-300 mt-2">
                                    The quick brown fox jumps over the lazy dog
                                </p>
                            </div>
                        @else
                            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Using default system font</p>
                            </div>
                        @endif
                    </div>

                </div>
            @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-full bg-orange-50 dark:bg-orange-500/15 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-2">No Branding Configured</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Set up your partner's visual identity to create a branded experience.</p>
                    <a href="{{ route('partners.edit', $partner) }}" class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-orange-600 dark:bg-orange-500 dark:hover:bg-orange-600">
                        Configure Branding
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Info Sidebar -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Quick Info</h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Slug</p>
                    <code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-gray-700 dark:text-gray-300">{{ $partner->slug }}</code>
                </div>
                @if($partner->domain)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Domain</p>
                        <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-500">
                            {{ $partner->domain }}
                        </span>
                    </div>
                @endif
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Created</p>
                    <p class="text-sm text-gray-800 dark:text-white/90">{{ $partner->created_at->format('F d, Y') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $partner->created_at->diffForHumans() }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Last Updated</p>
                    <p class="text-sm text-gray-800 dark:text-white/90">{{ $partner->updated_at->format('F d, Y') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $partner->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Section -->
<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-brand-50 dark:bg-brand-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Team Users</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $usersCount }} {{ Str::plural('user', $usersCount) }} assigned</p>
                </div>
            </div>
            <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-500">
                {{ $usersCount }}
            </span>
        </div>
    </div>
    <div class="p-6">
        @if($usersCount > 0)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full min-w-[600px]">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-3 text-left sm:px-6">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">User</p>
                                </th>
                                <th class="px-5 py-3 text-left sm:px-6">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Email</p>
                                </th>
                                <th class="px-5 py-3 text-left sm:px-6">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                                </th>
                                <th class="px-5 py-3 text-left sm:px-6">
                                    <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Joined</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($partner->users as $user)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex items-center gap-3">
                                            @if($user->profile_photo)
                                                <img src="{{ asset($user->profile_photo) }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-gray-200 dark:ring-gray-700">
                                            @else
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-400 to-orange-500 flex items-center justify-center text-white font-semibold ring-2 ring-gray-200 dark:ring-gray-700">
                                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $user->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $user->email }}</p>
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        @if($user->email_verified_at)
                                            <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500">
                                                Verified
                                            </span>
                                        @else
                                            <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-gray-50 text-gray-700 dark:bg-gray-500/15 dark:text-gray-400">
                                                Unverified
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 sm:px-6">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->created_at->format('M d, Y') }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $user->created_at->diffForHumans() }}</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-full bg-brand-50 dark:bg-brand-500/15 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-2">No Team Users Yet</h4>
                <p class="text-gray-600 dark:text-gray-400">Users assigned to this partner will appear here.</p>
            </div>
        @endif
    </div>
</div>
@endsection
