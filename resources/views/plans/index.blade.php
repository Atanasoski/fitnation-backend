@extends('layouts.app')

@section('title', 'Programs Library')

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    openCreateModal() { this.createModalOpen = true; },
    closeCreateModal() { this.createModalOpen = false; }
}" x-init="if ({{ Js::encode($errors->any()) }}) { createModalOpen = true; }">
    <x-common.page-breadcrumb
        pageTitle="Programs Library"
        :items="[]"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white">Programs Library</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Manage programs available to all gym members.</div>
        </div>
        <button type="button" @click="openCreateModal()" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
            Create Program
        </button>
    </div>

    <div class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900/30">
        @if($plans->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900">
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400" style="width: 300px;">Program Details</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Duration</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Templates</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Active</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Last Updated</th>
                            <th class="px-6 py-4 text-right font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($plans as $plan)
                            <tr class="group transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                            @if($plan->cover_image)
                                                <img src="{{ Storage::url($plan->cover_image) }}"
                                                    alt=""
                                                    class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-gray-400 dark:text-gray-500">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('partner.programs.show', $plan) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $plan->name }}
                                            </a>
                                            <span class="mt-1 line-clamp-1 block max-w-[250px] text-xs text-gray-500 dark:text-gray-400">
                                                {{ $plan->description ?? 'No description' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-700 dark:text-gray-300">
                                        {{ $plan->duration_weeks ? $plan->duration_weeks . ' weeks' : '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-700 dark:text-gray-300">
                                        {{ $plan->workout_templates_count }} {{ Str::plural('workout', $plan->workout_templates_count) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($plan->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Hidden</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                    {{ $plan->updated_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('partner.programs.show', $plan) }}"
                                            class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                            title="View">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($plans->hasPages())
                <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                    {{ $plans->links() }}
                </div>
            @endif
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No programs</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new program.</p>
                <div class="mt-6">
                    <button type="button" @click="openCreateModal()"
                        class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Program
                    </button>
                </div>
            </div>
        @endif
    </div>

    <x-modals.create-program :storeUrl="route('partner.programs.store')" />
</div>
@endsection
