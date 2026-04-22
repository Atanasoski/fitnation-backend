@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="space-y-6">
    <x-common.page-breadcrumb pageTitle="Users" />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                View and manage your gym users
            </p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="{{ route('user-invitations.index') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                User Invitations
            </a>
        </div>
    </div>

    <div
        x-data="{
            search: '',
            subscriptionStatus: '',
            openMenuId: null,
            menuTop: 0,
            menuLeft: 0,
            menuAssignUrl: '',
            menuCancelAction: '',
            menuPlansUrl: '',
            menuSubscriptionsUrl: '',
            toggleMenu(userId, event) {
                if (this.openMenuId === userId) {
                    this.openMenuId = null;
                    return;
                }
                const btn = event.currentTarget;
                const rect = btn.getBoundingClientRect();
                this.menuTop = rect.bottom + 4;
                this.menuLeft = rect.right;
                this.menuAssignUrl = btn.dataset.assignUrl || '';
                this.menuCancelAction = btn.dataset.cancelAction || '';
                this.menuPlansUrl = btn.dataset.plansUrl || '';
                this.menuSubscriptionsUrl = btn.dataset.subscriptionsUrl || '';
                this.openMenuId = userId;
            },
            page: 1,
            pageSize: 10,
            totalVisible: {{ $users->count() }},
            totalPages: 1,
            showingFrom: 0,
            showingTo: 0,
            applyFilters() {
                const q = this.search.toLowerCase().trim();
                const rows = document.querySelectorAll('.user-row');
                const matched = [];

                rows.forEach(row => {
                    const memberSearch = row.getAttribute('data-member-search') || '';
                    const rowStatus = row.getAttribute('data-membership-status') || '';

                    const matchesSearch = q === '' || memberSearch.includes(q);
                    const matchesStatus = this.subscriptionStatus === '' || rowStatus === this.subscriptionStatus;

                    if (matchesSearch && matchesStatus) {
                        matched.push(row);
                    } else {
                        row.classList.add('hidden');
                    }
                });

                this.totalVisible = matched.length;
                this.totalPages = Math.max(1, Math.ceil(this.totalVisible / this.pageSize));
                this.page = Math.min(Math.max(this.page, 1), this.totalPages);

                const startIndex = (this.page - 1) * this.pageSize;
                const endIndexExclusive = Math.min(startIndex + this.pageSize, this.totalVisible);

                this.showingFrom = this.totalVisible === 0 ? 0 : startIndex + 1;
                this.showingTo = endIndexExclusive;

                matched.forEach((row, index) => {
                    if (index >= startIndex && index < endIndexExclusive) {
                        row.classList.remove('hidden');
                    } else {
                        row.classList.add('hidden');
                    }
                });
            },
            clearFilters() {
                this.search = '';
                this.subscriptionStatus = '';
                this.page = 1;
                this.applyFilters();
                const input = document.getElementById('users-search');
                input?.focus();
            },
            init() {
                this.applyFilters();
            },
        }"
        class="space-y-6"
    >
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row">
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 103.5 10.5a7.5 7.5 0 0013.15 6.15z" />
                        </svg>
                    </div>
                    <input
                        id="users-search"
                        type="text"
                        placeholder="Search by name or email..."
                        x-model="search"
                        @input.debounce.250ms="page = 1; applyFilters()"
                        class="block w-full rounded-md border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm leading-5 text-gray-900 placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-transparent dark:text-white dark:placeholder:text-gray-500"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative">
                        <select
                            x-model="subscriptionStatus"
                            @change="page = 1; applyFilters()"
                            class="appearance-none block w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-sm leading-5 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-transparent dark:text-white"
                        >
                            <option value="">All members</option>
                            <option value="active">Active membership</option>
                            <option value="inactive">No active membership</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="clearFilters()"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-white dark:hover:bg-white/5"
                    >
                        Clear
                    </button>
                </div>
            </div>

            @if($users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 table-fixed dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="w-[15%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                                <th class="w-[17%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Email</th>
                                <th class="w-[10%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Joined</th>
                                <th class="w-[9%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Login</th>
                                <th class="w-[8%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Workouts</th>
                                <th class="w-[9%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Program</th>
                                <th class="w-[14%] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Membership</th>
                                <th class="w-[5%] px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($users as $user)
                                <tr
                                    class="user-row hover:bg-gray-50 dark:hover:bg-gray-700"
                                    data-member-search="{{ str($user->name.' '.$user->email)->lower() }}"
                                    data-membership-status="{{ $user->memberSubscriptionFilterStatus() }}"
                                >
                                    <td class="px-4 py-4">
                                        <div class="flex min-w-0 items-center">
                                            @if($user->profile_photo)
                                                <img src="{{ $user->profile_photo }}" alt="{{ $user->name }}" class="mr-3 h-8 w-8 rounded-full object-cover">
                                            @else
                                                <div class="mr-3 flex h-8 w-8 items-center justify-center rounded-full bg-blue-500 text-sm font-medium text-white">
                                                    {{ substr($user->name, 0, 1) }}
                                                </div>
                                            @endif
                                            <div class="truncate">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <a href="{{ route('users.show', $user) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                        {{ $user->name }}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900 dark:text-white truncate">
                                        {{ $user->email }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->created_at->format('M d, Y') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center text-sm text-gray-900 dark:text-white">
                                        {{ $user->total_workouts ?? 0 }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            @if($user->activeProgram)
                                                <a href="{{ route('plans.show', $user->activeProgram) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $user->activeProgram->name }}
                                                </a>
                                            @else
                                                None
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($user->activeSubscription)
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\Subscription::badgeClassesForDerivedState('active') }}">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ \App\Models\Subscription::badgeClassesForDerivedState('inactive') }}">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm font-medium">
                                        <button
                                            type="button"
                                            @click="toggleMenu({{ $user->id }}, $event)"
                                            data-assign-url="{{ route('subscriptions.create', ['user_id' => $user->id, 'redirect_after' => 'users']) }}"
                                            @if($user->activeSubscription) data-cancel-action="{{ route('subscriptions.destroy', $user->activeSubscription) }}" @endif
                                            data-plans-url="{{ route('plans.index', $user) }}"
                                            data-subscriptions-url="{{ route('users.subscriptions.index', $user) }}"
                                            class="inline-flex items-center justify-center rounded-md bg-gray-100 p-2 text-gray-900 ring-1 ring-gray-200 transition-colors hover:bg-gray-200 dark:bg-gray-900/40 dark:text-white dark:ring-gray-700 dark:hover:bg-gray-900/60"
                                            aria-label="Open actions menu"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <circle cx="10" cy="4" r="1.5" />
                                                <circle cx="10" cy="10" r="1.5" />
                                                <circle cx="10" cy="16" r="1.5" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach

                            <tr x-show="totalVisible === 0" x-cloak>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No users match your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700" x-show="totalVisible > pageSize" x-cloak>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Showing <span class="font-medium text-gray-900 dark:text-white" x-text="showingFrom"></span>–<span class="font-medium text-gray-900 dark:text-white" x-text="showingTo"></span>
                            of <span class="font-medium text-gray-900 dark:text-white" x-text="totalVisible"></span>
                            <span class="hidden sm:inline">|</span>
                            <span class="sm:ml-1">Page <span class="font-medium text-gray-900 dark:text-white" x-text="page"></span> of <span class="font-medium text-gray-900 dark:text-white" x-text="totalPages"></span></span>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-white dark:hover:bg-white/5"
                                :disabled="page <= 1"
                                @click="page = Math.max(1, page - 1); applyFilters()"
                            >
                                Prev
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-white dark:hover:bg-white/5"
                                :disabled="page >= totalPages"
                                @click="page = Math.min(totalPages, page + 1); applyFilters()"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No users yet. Start by inviting users!</p>
                </div>
            @endif
        </div>

        {{-- Single fixed-position actions dropdown --}}
        <div
            x-show="openMenuId !== null"
            x-cloak
            @click.outside="openMenuId = null"
            class="fixed z-50 w-44 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
            :style="'top:' + menuTop + 'px; left:' + (menuLeft - 176) + 'px'"
        >
            <template x-if="menuAssignUrl">
                <button
                    type="button"
                    @click="openMenuId = null; window.location = menuAssignUrl"
                    class="block w-full px-4 py-2 text-left text-sm text-blue-600 hover:bg-gray-50 dark:text-blue-400 dark:hover:bg-gray-800"
                >
                    Assign
                </button>
            </template>

            <template x-if="menuSubscriptionsUrl">
                <button
                    type="button"
                    @click="openMenuId = null; window.location = menuSubscriptionsUrl"
                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Subscriptions
                </button>
            </template>

            <template x-if="menuCancelAction">
                <form :action="menuCancelAction" method="POST" onsubmit="return confirm('Cancel this subscription?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-50 dark:text-red-400 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                </form>
            </template>

            <button
                type="button"
                @click="openMenuId = null; window.location = menuPlansUrl"
                class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                Plans
            </button>
        </div>
    </div>
</div>
@endsection
