<?php

namespace App\Notifications\Channels;

use App\Models\Device;
use App\Notifications\Messages\ExpoMessage;
use App\Services\Notifications\Expo;
use App\Services\Notifications\ExpoErrors;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The one seam between the domain and the Expo Push Service.
 *
 * A notification that wants to be pushed implements toExpo(): ExpoMessage and
 * lists 'expo' after 'database' in via(). The database row must exist first:
 * the ticket ids Expo hands back are written onto it, under
 * data.expo_tickets, so that FetchExpoReceipts can later ask how each one
 * fared without a table of its own.
 *
 * Which of the recipient's Devices are addressed is Device::pushable()'s
 * decision — the Push Switch and the build-profile fence live there, not here.
 * With NOTIFICATIONS_ENABLED off, the request that would have been made is
 * logged in full and nothing is sent.
 */
final class ExpoChannel
{
    /** Expo's documented maximum per request. */
    private const CHUNK = 100;

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpo')) {
            throw new LogicException($notification::class.' lists the expo channel but has no toExpo(): ExpoMessage.');
        }

        $devices = $notifiable->devices()->pushable()->get();

        if ($devices->isEmpty()) {
            return;
        }

        $message = $notification->toExpo($notifiable);

        if (! config('notifications.enabled')) {
            Log::info('Push suppressed: NOTIFICATIONS_ENABLED is off', [
                'notification' => $notification::class,
                'messages' => $devices->map(fn (Device $device) => $message->toArray($device->push_token))->all(),
            ]);

            return;
        }

        $tickets = [];

        foreach ($devices->chunk(self::CHUNK) as $chunk) {
            $tickets += $this->push($chunk->values(), $message);
        }

        $this->recordTickets($notifiable, $notification, $tickets);
    }

    /**
     * One request to Expo for up to CHUNK Devices. Expo answers with one ticket
     * per message, in order.
     *
     * @param  Collection<int, Device>  $devices
     * @return array<int, string> device id => ticket id, for the ones accepted
     */
    private function push(Collection $devices, ExpoMessage $message): array
    {
        $results = Expo::send($devices->map(
            fn (Device $device) => $message->toArray($device->push_token)
        )->all());

        $tickets = [];

        foreach ($devices as $index => $device) {
            $result = $results[$index] ?? null;

            if ($result === null) {
                continue;
            }

            if (($result['status'] ?? null) === 'ok' && isset($result['id'])) {
                $tickets[$device->id] = $result['id'];
            } else {
                ExpoErrors::fromTicket($device, $result);
            }
        }

        return $tickets;
    }

    /**
     * @param  array<int, string>  $tickets
     */
    private function recordTickets(object $notifiable, Notification $notification, array $tickets): void
    {
        if ($tickets === []) {
            return;
        }

        $row = $notifiable->notifications()->find($notification->id);

        if ($row === null) {
            return;
        }

        $row->data = [...$row->data, 'expo_tickets' => $tickets];
        $row->save();
    }
}
