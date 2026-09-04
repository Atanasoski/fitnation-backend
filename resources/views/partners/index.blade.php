@extends('layouts.app')

@section('title', 'Partners')

@section('content')
<!-- Breadcrumb -->
<x-common.page-breadcrumb pageTitle="Partners" />

<!-- Page Header -->
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    <div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Manage your partners and their configurations
        </p>
    </div>
    <div>
        <a href="{{ route('partners.create') }}" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
            CREATE PARTNER
        </a>
    </div>
</div>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
    <div class="max-w-full overflow-x-auto custom-scrollbar">
        <table class="w-full min-w-[1102px]">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Name
                        </p>
                    </th>
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Slug
                        </p>
                    </th>
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Domain
                        </p>
                    </th>
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Branding
                        </p>
                    </th>
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Status
                        </p>
                    </th>
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Users
                        </p>
                    </th>
                    <th class="px-5 py-3 text-left sm:px-6">
                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                            Actions
                        </p>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($partners as $partner)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-5 py-4 sm:px-6">
                            <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                {{ $partner->name }}
                            </p>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <code class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $partner->slug }}</code>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            @if($partner->domain)
                                <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-500">
                                    {{ $partner->domain }}
                                </span>
                            @else
                                <span class="text-gray-400 text-theme-sm dark:text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            @if($partner->identity)
                                <div class="flex gap-2 items-center">
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $partner->identity->primary_color }};"></div>
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $partner->identity->secondary_color }};"></div>
                                </div>
                            @else
                                <span class="text-gray-400 text-theme-sm dark:text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            @if($partner->is_active)
                                <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500">
                                    Active
                                </span>
                            @else
                                <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-gray-50 text-gray-700 dark:bg-gray-500/15 dark:text-gray-400">
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <span class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-500">
                                {{ $partner->users_count }}
                            </span>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('partners.show', $partner) }}" class="text-theme-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                    VIEW
                                </a>
                                <span class="text-gray-300 dark:text-gray-700">|</span>
                                <a href="{{ route('partners.edit', $partner) }}" class="text-theme-xs font-medium text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300">
                                    EDIT
                                </a>
                                <span class="text-gray-300 dark:text-gray-700">|</span>
                                <form action="{{ route('partners.destroy', $partner) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this partner?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-theme-xs font-medium text-error-600 hover:text-error-700 dark:text-error-400 dark:hover:text-error-300">
                                        DELETE
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center sm:px-6">
                            <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                No partners found. <a href="{{ route('partners.create') }}" class="text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 underline">Create your first partner</a>.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
