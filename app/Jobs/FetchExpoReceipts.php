<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Notifications\Expo;
use App\Services\Notifications\ExpoErrors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Ask Expo how the pushes we sent a little while ago actually fared.
 *
 * A push request is answered with tickets, not outcomes; the outcome — a
 * receipt — exists on Expo's side some minutes later and for about a day.
 * ExpoChannel leaves each notification's ticket ids on its database row under
 * data.expo_tickets; this job, every fifteen minutes, takes the rows that are
 * old enough to have receipts and young enough that a stalled worker has not
 * let them go stale, fetches the receipts, hands every failure to ExpoErrors
 * (a dead Push Token ends its Device; anything else is logged) and strips the
 * tickets it got answers for, so a row is asked about each ticket once. A
 * ticket Expo has no receipt for yet stays on the row and is asked about on
 * the next run, until the row ages out of the window.
 */
final class FetchExpoReceipts implements ShouldQueue
{
    use Queueable;

    /** Expo's documented maximum ids per receipts request. */
    private const CHUNK = 1000;

    /** Receipts are not reliably available sooner than this. */
    private const YOUNGEST_MINUTES = 15;

    /** Receipts expire on Expo's side after ~24h; this leaves slack for a stalled worker without letting the window grow. */
    private const OLDEST_MINUTES = 90;

    public function handle(): void
    {
        $rows = DatabaseNotification::query()
            ->whereBetween('created_at', [
                now()->subMinutes(self::OLDEST_MINUTES),
                now()->subMinutes(self::YOUNGEST_MINUTES),
            ])
            ->whereNotNull('data->expo_tickets')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        // ticket id => device id, across every row in the window
        $devicesByTicket = [];

        foreach ($rows as $row) {
            foreach ($row->data['expo_tickets'] ?? [] as $deviceId => $ticketId) {
                $devicesByTicket[$ticketId] = (int) $deviceId;
            }
        }

        $devices = Device::query()->findMany(array_values($devicesByTicket))->keyBy('id');
        $answered = [];

        foreach (array_chunk(array_keys($devicesByTicket), self::CHUNK) as $ids) {
            foreach (Expo::receipts($ids) as $ticketId => $receipt) {
                $answered[$ticketId] = true;

                if (($receipt['status'] ?? null) === 'ok') {
                    continue;
                }

                ExpoErrors::fromReceipt($devices->get($devicesByTicket[$ticketId] ?? null), $receipt);
            }
        }

        foreach ($rows as $row) {
            $data = $row->data;
            $pending = array_filter($data['expo_tickets'] ?? [], fn (string $ticketId) => ! isset($answered[$ticketId]));

            if ($pending === []) {
                unset($data['expo_tickets']);
            } else {
                $data['expo_tickets'] = $pending;
            }

            $row->data = $data;
            $row->save();
        }
    }
}
