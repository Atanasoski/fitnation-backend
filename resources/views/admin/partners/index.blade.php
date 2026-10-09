@extends('layouts.app')

@section('title', 'Partners')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">
            Partners
            @if ($expiring)
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">sponsorship running out within 30 days · <a href="{{ route('admin.partners.index') }}" class="text-brand-600 hover:underline dark:text-brand-400">show all</a></span>
            @endif
        </h1>
        <a href="{{ route('partners.create') }}" class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
            New partner
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-900/20">
            <p class="text-sm text-success-800 dark:text-success-200">{{ session('success') }}</p>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($partners->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No partners yet.</p>
        @else
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-theme-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-2 font-medium sm:px-6">Partner</th>
                            <th class="px-3 py-2 font-medium">Kind</th>
                            <th class="px-3 py-2 font-medium">Members</th>
                            <th class="px-3 py-2 font-medium">Active this week</th>
                            <th class="px-3 py-2 font-medium">Plan</th>
                            <th class="px-3 py-2 font-medium">Sponsorship until</th>
                            <th class="px-5 py-2 font-medium sm:px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partners as $partner)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-3 sm:px-6">
                                    <a href="{{ route('admin.partners.show', $partner) }}" class="font-medium text-gray-800 hover:text-brand-600 hover:underline dark:text-white/90 dark:hover:text-brand-400">{{ $partner->name }}</a>
                                </td>
                                <td class="px-3 py-3 text-gray-600 dark:text-gray-400">{{ $partner->kind()->label() }}</td>
                                <td class="px-3 py-3 tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($partner->members_count) }}</td>
                                <td class="px-3 py-3 tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($partner->active_this_week_count) }}</td>
                                <td class="px-3 py-3 text-gray-600 dark:text-gray-400">{{ $partner->plan ? ucfirst($partner->plan->value) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-gray-600 dark:text-gray-400">{{ $partner->plan_expires_at?->format('j M Y') ?? '—' }}</td>
                                <td class="px-5 py-3 sm:px-6">@include('admin.partners._status', ['partner' => $partner])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
