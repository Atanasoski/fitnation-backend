<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;

/**
 * The two calls this codebase makes to the Expo Push Service, and nothing
 * else about it. Both carry the project's push-security token and retry
 * transient failures; both return Expo's `data` payload as-is, so callers read
 * tickets and receipts in the shape Expo documents.
 */
final class Expo
{
    /**
     * Send up to 100 messages. Expo answers with one ticket per message, in
     * the order they were sent.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    public static function send(array $messages): array
    {
        return self::post(config('notifications.expo.send_url'), $messages);
    }

    /**
     * Ask after up to 1000 tickets. Expo answers with a receipt per ticket id it
     * has one for; ids it has nothing for yet are simply absent.
     *
     * @param  list<string>  $ticketIds
     * @return array<string, array<string, mixed>>
     */
    public static function receipts(array $ticketIds): array
    {
        return self::post(config('notifications.expo.receipts_url'), ['ids' => $ticketIds]);
    }

    /**
     * @param  array<mixed>  $body
     * @return array<mixed>
     */
    private static function post(string $url, array $body): array
    {
        return Http::withToken(config('notifications.expo.access_token'))
            ->acceptJson()
            ->retry(3, 500)
            ->post($url, $body)
            ->throw()
            ->json('data', []);
    }
}
