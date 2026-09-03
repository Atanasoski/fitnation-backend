<?php

namespace App\Notifications;

use App\Notifications\Messages\ExpoMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * An ad-hoc push for a human holding a phone — `php artisan push:test`. Not
 * queued, so the command can read the tickets back as soon as notify() returns.
 *
 * It chooses its own id: the notification sender works on a clone and would
 * otherwise assign the id to that, leaving the instance the command holds
 * without one. A pre-set id is kept, so it is also the database row's id.
 */
class TestPush extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url = 'fitnation://dashboard',
    ) {
        $this->id = (string) Str::uuid();
    }

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
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toExpo(object $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: $this->title,
            body: $this->body,
            data: ['url' => $this->url],
        );
    }
}
