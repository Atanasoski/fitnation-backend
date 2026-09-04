@extends('layouts.app')

@section('title', 'Plans - ' . $user->name)

@section('content')
<div class="space-y-6" x-data="{
    editModalOpen: false,
    editingPlan: null,
    editPlanSubmitting: false,
    createPlanModalOpen: false,
    openEditModal(planData) {
        this.editingPlan = typeof planData === 'string' ? JSON.parse(planData) : planData;
        this.editModalOpen = true;
        this.$nextTick(() => {
            if (typeof window.initFilePond === 'function' && this.$refs.editPlanModalContent) {
                window.initFilePond(this.$refs.editPlanModalContent);
            }
        });
    },
    closeEditModal() {
        this.editModalOpen = false;
        this.editingPlan = null;
        this.editPlanSubmitting = false;
    },
    openCreatePlanModal() { this.createPlanModalOpen = true; },
    closeCreatePlanModal() { this.createPlanModalOpen = false; }
}" x-init="if ({{ Js::encode($errors->any()) }}) { createPlanModalOpen = true; }">
    <x-common.page-breadcrumb
        pageTitle="Plans"
        :items="[
            ['label' => 'Users', 'url' => route('users.index')],
            ['label' => $user->name, 'url' => route('users.show', $user)],
        ]"
    />

    @if ($errors->any())
        <div class="rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
            <div class="mb-2 text-sm font-semibold text-error-800 dark:text-error-400">There were some errors with your submission:</div>
            <ul class="list-inside list-disc space-y-1 text-sm text-error-700 dark:text-error-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white">Plans for {{ $user->name }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Manage and create plans for this user.</div>
        </div>
        <button type="button" @click="openCreatePlanModal()" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
            Create Plan
        </button>
    </div>

    <div class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900/30">
        @if($plans->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900">
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400" style="width: 300px;">Plan Details</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Templates</th>
                            <th class="px-6 py-4 font-medium text-gray-500 dark:text-gray-400">Last Updated</th>
                            <th class="px-6 py-4 text-right font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($plans as $plan)
                            @php
                                $planModalData = [
                                    'id' => $plan->id,
                                    'name' => $plan->name,
                                    'description' => $plan->description ?? '',
                                    'type' => 'program',
                                    'duration_weeks' => $plan->duration_weeks,
                                    'is_active' => (bool) $plan->is_active,
                                    'update_url' => route('plans.update', $plan),
                                    'cover_image_url' => $plan->cover_image ? Storage::url($plan->cover_image) : null,
                                ];
                            @endphp
                            <tr class="group transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-700"
                                data-plan="{{ json_encode($planModalData) }}">
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
                                            <a href="{{ route('plans.show', $plan) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-400 dark:hover:text-brand-300">
                                                {{ $plan->name }}
                                            </a>
                                            <span class="mt-1 line-clamp-1 block max-w-[250px] text-xs text-gray-500 dark:text-gray-400">
                                                {{ $plan->description ?? 'No description' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($plan->is_active)
                                        <span class="inline-flex items-center rounded-full border border-success-200 bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 dark:border-success-900/40 dark:bg-success-900/30 dark:text-success-300">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-error-200 bg-error-50 px-2.5 py-0.5 text-xs font-medium text-error-700 dark:border-error-900/40 dark:bg-error-900/30 dark:text-error-300">
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center text-gray-600 dark:text-gray-300">
                                        <span class="font-medium">{{ $plan->workout_templates_count ?? 0 }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col text-xs">
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ $plan->updated_at->diffForHumans() }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button"
                                            @click="openEditModal($el.closest('tr').dataset.plan)"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                                            title="Edit Plan">
                                            <svg class="h-4 w-4 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-800" title="Delete">
                                                <svg class="h-4 w-4 text-gray-400 hover:text-error-500 dark:text-gray-500 dark:hover:text-error-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Table Footer / Pagination -->
            <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50/50 px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <span class="font-medium text-gray-900 dark:text-white">{{ $plans->firstItem() }}</span>-<span class="font-medium text-gray-900 dark:text-white">{{ $plans->lastItem() }}</span> of <span class="font-medium text-gray-900 dark:text-white">{{ $plans->total() }}</span> plans
                </span>
                <div class="flex gap-2">
                    @if($plans->onFirstPage())
                        <span class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-600">
                            Previous
                        </span>
                    @else
                        <a href="{{ $plans->previousPageUrl() }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                            Previous
                        </a>
                    @endif
                    @if($plans->hasMorePages())
                        <a href="{{ $plans->nextPageUrl() }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                            Next
                        </a>
                    @else
                        <span class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-600">
                            Next
                        </span>
                    @endif
                </div>
            </div>
        @else
            <div class="p-10 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No plans</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new plan for this user.</p>
                <div class="mt-6">
                    <button type="button" @click="openCreatePlanModal()" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                        Create Plan
                    </button>
                </div>
            </div>
        @endif
    </div>

    <x-modals.edit-plan :subtitle="'Update plan details for ' . $user->name . '.'" />
    <x-modals.create-plan :user="$user" :storeUrl="route('plans.store', $user)" />
</div>
@endsection

