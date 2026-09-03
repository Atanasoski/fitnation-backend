<?php

namespace App\Services\Notifications;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/**
 * What to do when Expo says a push did not go through.
 *
 * Expo reports failures in two places with one shape — on the ticket at send
 * time, and on the receipt fetched later — so both readers hand the error here.
 * One outcome is domain-significant: `DeviceNotRegistered` means the Push Token
 * is dead, and a dead token ends the Device that held it (CONTEXT.md,
 * ADR-0003). The session itself is untouched; an uninstalled app is not a
 * request to be logged out. Everything else is logged for a human.
 */
final class ExpoErrors
{
    public const DEVICE_NOT_REGISTERED = 'DeviceNotRegistered';

    /**
     * @param  array<string, mixed>  $ticket  an Expo ticket with `status` = error
     */
    public static function fromTicket(?Device $device, array $ticket): void
    {
        self::handle($device, $ticket, 'ticket');
    }

    /**
     * @param  array<string, mixed>  $receipt  an Expo receipt with `status` = error
     */
    public static function fromReceipt(?Device $device, array $receipt): void
    {
        self::handle($device, $receipt, 'receipt');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function handle(?Device $device, array $result, string $stage): void
    {
        $code = $result['details']['error'] ?? null;

        if ($code === self::DEVICE_NOT_REGISTERED) {
            $device?->delete();

            return;
        }

        Log::warning("Expo push {$stage} failed", [
            'device_id' => $device?->id,
            'error' => $code,
            'message' => $result['message'] ?? null,
        ]);
    }
}
