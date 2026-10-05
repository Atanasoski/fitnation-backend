@extends('layouts.app')

@section('title', 'System')

@section('content')
    <x-common.page-breadcrumb pageTitle="System" />

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-900/20">
            <p class="text-sm text-success-800 dark:text-success-200">{{ session('success') }}</p>
        </div>
    @endif

    <div class="space-y-6">
        {{-- Failed queue jobs --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <header class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div>
                    <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Failed jobs</h2>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                        Queue jobs that ran out of attempts. Retry puts one back on its queue.
                    </p>
                </div>
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                    {{ $failedJobCount }}
                </span>
            </header>

            @if ($failedJobs->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No failed jobs. Everything on the queue went through.</p>
            @else
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-theme-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-2 font-medium sm:px-6">Job</th>
                                <th class="px-3 py-2 font-medium">Queue</th>
                                <th class="px-3 py-2 font-medium">Failed</th>
                                <th class="px-3 py-2 font-medium">Error</th>
                                <th class="px-5 py-2 sm:px-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($failedJobs as $job)
                                <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                    <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90 sm:px-6">{{ $job['job'] }}</td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-400">
                                        <code>{{ $job['queue'] }}</code>
                                        <span class="block text-theme-xs text-gray-400">{{ $job['connection'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-gray-600 dark:text-gray-400" title="{{ $job['failed_at']->toDateTimeString() }}">
                                        {{ $job['failed_at']->diffForHumans() }}
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-400">
                                        <span class="line-clamp-2 break-all">{{ $job['error'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right sm:px-6">
                                        <form method="POST" action="{{ route('admin.system.failed-jobs.retry', $job['id']) }}" class="inline"
                                            onsubmit="return confirm('Retry this job? It goes back on the {{ $job['queue'] }} queue.')">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-theme-xs font-medium text-white hover:bg-brand-600">Retry</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.system.failed-jobs.destroy', $job['id']) }}" class="inline"
                                            onsubmit="return confirm('Delete this failed job? It will not run again.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($failedJobCount > $failedJobs->count())
                    <p class="border-t border-gray-100 px-5 py-3 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400 sm:px-6">
                        Showing the newest {{ $failedJobs->count() }} of {{ $failedJobCount }}.
                    </p>
                @endif
            @endif
        </section>

        {{-- Failed RevenueCat webhook calls --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <header class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div>
                    <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Failed webhooks</h2>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                        RevenueCat calls whose processing failed. Replay runs them again; replay is safe to repeat.
                    </p>
                </div>
                @if ($failedWebhooks->total() > 0)
                    <form method="POST" action="{{ route('admin.system.webhooks.replay-all') }}"
                        onsubmit="return confirm('Replay all {{ $failedWebhooks->total() }} failed webhook call(s)?')">
                        @csrf
                        <button type="submit" class="whitespace-nowrap rounded-lg bg-brand-500 px-3 py-1.5 text-theme-xs font-medium text-white hover:bg-brand-600">
                            Replay all ({{ $failedWebhooks->total() }})
                        </button>
                    </form>
                @endif
            </header>

            @if ($failedWebhooks->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No failed webhooks. Subscription state is up to date.</p>
            @else
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-theme-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-2 font-medium sm:px-6">Call</th>
                                <th class="px-3 py-2 font-medium">Event</th>
                                <th class="px-3 py-2 font-medium">Received</th>
                                <th class="px-3 py-2 font-medium">Error</th>
                                <th class="px-5 py-2 sm:px-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($failedWebhooks as $call)
                                <tr class="border-t border-gray-100 align-top dark:border-gray-800">
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-400 sm:px-6">#{{ $call->id }}</td>
                                    <td class="px-3 py-3 font-medium text-gray-800 dark:text-white/90">
                                        <code>{{ $call->payload['event']['type'] ?? 'Unknown' }}</code>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-gray-600 dark:text-gray-400" title="{{ $call->created_at?->toDateTimeString() }}">
                                        {{ $call->created_at?->diffForHumans() }}
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-400">
                                        <span class="line-clamp-2 break-all">{{ strtok((string) ($call->exception['message'] ?? ''), "\n") }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right sm:px-6">
                                        <form method="POST" action="{{ route('admin.system.webhooks.replay', $call->id) }}" class="inline"
                                            onsubmit="return confirm('Replay webhook call #{{ $call->id }}?')">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Replay</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($failedWebhooks->hasPages())
                    <div class="border-t border-gray-100 px-5 py-3 dark:border-gray-800 sm:px-6">
                        {{ $failedWebhooks->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
