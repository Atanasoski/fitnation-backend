<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "You never finished" — one step of the Unfinished Account ladder (CONTEXT.md).
 * The step is recorded on the database row; that record is how
 * App\Services\Notifications\UnfinishedAccounts knows it was already sent.
 */
class UnfinishedAccountNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $step,
        public readonly string $stuckAt,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'step' => $this->step,
            'stuck_at' => $this->stuckAt,
        ];
    }
}
