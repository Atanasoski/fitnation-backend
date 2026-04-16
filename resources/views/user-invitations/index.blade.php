@extends('layouts.app')

@section('title', 'User Invitations')

@section('content')
<div class="p-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb pageTitle="User Invitations" />

    <!-- Page Header -->
    <div class="mb-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Invite new users to join your gym
        </p>
    </div>

    <!-- Invite New User Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Invite New User
        </h3>

        <form method="POST" action="{{ route('user-invitations.invite') }}" class="flex gap-3">
            @csrf
            <div class="flex-1">
                <input 
                    type="email" 
                    name="email" 
                    placeholder="user@example.com" 
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                    value="{{ old('email') }}"
                    required>
                @error('email')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <button 
                type="submit" 
                class="px-6 py-2 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors inline-flex items-center dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Send Invitation
            </button>
        </form>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            An email invitation will be sent to the user with a signup link. Invitations expire in 7 days.
        </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6" x-data="{ activeTab: 'pending' }">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex gap-4">
                <button 
                    @click="activeTab = 'pending'"
                    :class="activeTab === 'pending' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors">
                    Pending Invitations ({{ $pendingInvitations->count() }})
                </button>
                <button 
                    @click="activeTab = 'expired'"
                    :class="activeTab === 'expired' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors">
                    Expired ({{ $expiredInvitations->count() }})
                </button>
            </nav>
        </div>

        <!-- Pending Invitations Tab -->
        <div x-show="activeTab === 'pending'" class="mt-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                @if($pendingInvitations->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($pendingInvitations as $invitation)
                            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-yellow-500 flex items-center justify-center text-white font-bold mr-3">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $invitation->email }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Invited by {{ $invitation->inviter->name }} • {{ $invitation->created_at->diffForHumans() }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Expires {{ $invitation->expires_at->diffForHumans() }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <!-- Resend -->
                                        <form method="POST" action="{{ route('user-invitations.resend', $invitation) }}" class="inline">
                                            @csrf
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 text-sm bg-gray-900 text-white rounded hover:bg-gray-800 transition-colors dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                                                Resend
                                            </button>
                                        </form>
                                        <!-- Cancel -->
                                        <form method="POST" action="{{ route('user-invitations.destroy', $invitation) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                                                onclick="return confirm('Are you sure you want to cancel this invitation?')">
                                                Cancel
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No pending invitations</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Expired Invitations Tab -->
        <div x-show="activeTab === 'expired'" class="mt-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                @if($expiredInvitations->count() > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($expiredInvitations as $invitation)
                            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white font-bold mr-3">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $invitation->email }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Invited by {{ $invitation->inviter->name }} • Expired {{ $invitation->expires_at->diffForHumans() }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <form method="POST" action="{{ route('user-invitations.resend', $invitation) }}" class="inline">
                                            @csrf
                                            <button 
                                                type="submit" 
                                                class="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600 transition-colors">
                                                Resend New Invitation
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No expired invitations in the last 30 days</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
