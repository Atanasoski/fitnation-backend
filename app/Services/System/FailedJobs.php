<?php

namespace App\Services\System;

use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Failed queue jobs as the System page shows them, and the two things an admin
 * can do with one: retry it (Laravel's queue:retry, so it goes back on its own
 * connection and queue) or forget it.
 */
final class FailedJobs
{
    public function __construct(private FailedJobProviderInterface $failer) {}

    /**
     * Newest first.
     *
     * @return Collection<int, array{id: string, job: string, connection: string, queue: string, failed_at: Carbon, error: string}>
     */
    public function latest(int $limit = 50): Collection
    {
        return collect($this->failer->all())
            ->take($limit)
            ->map(fn (object $record) => $this->present($record))
            ->values();
    }

    public function count(): int
    {
        return $this->failer instanceof CountableFailedJobProvider
            ? $this->failer->count()
            : count($this->failer->all());
    }

    public function exists(string $id): bool
    {
        return $this->failer->find($id) !== null;
    }

    public function retry(string $id): void
    {
        Artisan::call('queue:retry', ['id' => [$id]]);
    }

    public function forget(string $id): void
    {
        $this->failer->forget($id);
    }

    /**
     * @return array{id: string, job: string, connection: string, queue: string, failed_at: Carbon, error: string}
     */
    private function present(object $record): array
    {
        $payload = json_decode($record->payload, true) ?: [];

        return [
            'id' => (string) $record->id,
            'job' => class_basename($payload['displayName'] ?? 'Unknown job'),
            'connection' => (string) $record->connection,
            'queue' => (string) $record->queue,
            'failed_at' => Carbon::parse($record->failed_at),
            'error' => strtok((string) $record->exception, "\n") ?: '',
        ];
    }
}
