@extends('layouts.app')

@section('title', 'Gym Dashboard')

@section('content')
<div class="p-6">
    <!-- Welcome Header with Gym Branding -->
    <div class="mb-6">
        <div class="flex items-center mb-3">
            @if($partner->identity && $partner->identity->logo_url)
                <img src="{{ $partner->identity->logo_url }}" alt="{{ $partner->name }}" class="w-16 h-16 rounded mr-4 object-cover">
            @endif
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $partner->name }} Dashboard
                </h2>
                <p class="text-gray-600 dark:text-gray-400">Welcome back, {{ Auth::user()->name }} - Gym Manager</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <!-- Total Users -->
        <div class="bg-brand-500 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center">
                <div class="shrink-0">
                    <svg class="w-12 h-12 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-3xl font-bold">{{ $totalMembers }}</h3>
                    <p class="text-sm opacity-90">Total Users</p>
                </div>
            </div>
        </div>

        <!-- Active This Week -->
        <div class="bg-success-500 rounded-lg shadow-sm p-6 text-white">
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

    <!-- Main Content Row: Top Active Users + Recent User Signups side by side -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Active Users -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                <h5 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-warning-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                    Top Active Users This Week
                </h5>
            </div>
            <div class="p-0">
                @if($topMembers->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($topMembers as $index => $member)
                            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="mr-4 text-center" style="width: 30px;">
                                            @if($index < 3)
                                                <svg class="w-6 h-6 text-warning-500" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                                </svg>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400 font-bold">{{ $index + 1 }}</span>
                                            @endif
                                        </div>
                                        @if($member->profile_photo)
                                            <img src="{{ $member->profile_photo }}" alt="{{ $member->name }}" class="w-10 h-10 rounded-full mr-3 object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full mr-3 bg-success-500 flex items-center justify-center text-white font-bold">
                                                {{ substr($member->name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $member->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->email }}</div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100">{{ $member->workout_sessions_count }} workouts</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No active users this week</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent User Signups -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                <h5 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-success-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    Recent User Signups
                </h5>
            </div>
            <div class="p-0">
                @if($recentMembers->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentMembers as $member)
                            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        @if($member->profile_photo)
                                            <img src="{{ $member->profile_photo }}" alt="{{ $member->name }}" class="w-10 h-10 rounded-full mr-3 object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full mr-3 bg-brand-500 flex items-center justify-center text-white font-bold">
                                                {{ substr($member->name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $member->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->email }}</div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No users yet</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
