<?php

namespace App\Notifications;

use App\Notifications\Messages\ExpoMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "You have not trained in a while" — one step of the Inactivity Nudge ladder
 * (CONTEXT.md). The step is recorded on the database row; that record is how
 * App\Services\Notifications\Inactivity knows it was already sent.
 */
class InactivityNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $step) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'expo'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'step' => $this->step,
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => config('notifications.urls.dashboard'),
        ];
    }

    public function toExpo(object $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: $this->title(),
            body: $this->body(),
            data: ['url' => config('notifications.urls.dashboard')],
        );
    }

    private function title(): string
    {
        return $this->copy('title');
    }

    private function body(): string
    {
        return $this->copy('body');
    }

    private function copy(string $key): string
    {
        return __("notifications.inactivity.{$this->step}.{$key}");
    }
}
