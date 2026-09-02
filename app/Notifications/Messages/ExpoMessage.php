<?php

namespace App\Notifications\Messages;

/**
 * What one push says, independent of who receives it. The channel addresses it
 * to each of the recipient's Devices with toArray().
 */
final class ExpoMessage
{
    /**
     * @param  array<string, mixed>  $data  delivered to the app on tap; `url` is what the app routes on
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly string $channelId = 'default',
        public readonly ?string $sound = 'default',
        public readonly ?int $badge = null,
    ) {}

    /**
     * One entry of an Expo push request, addressed to one Push Token.
     *
     * @return array<string, mixed>
     */
    public function toArray(string $to): array
    {
        return array_filter([
            'to' => $to,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'channelId' => $this->channelId,
            'sound' => $this->sound,
            'badge' => $this->badge,
        ], fn ($value) => $value !== null);
    }
}
