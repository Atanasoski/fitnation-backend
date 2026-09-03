<?php

namespace App\Services\Notifications;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The single write path for Devices.
 *
 * A Device is the Sanctum token it registered under (ADR-0003), so registering
 * is an upsert keyed by that token: the same session reporting again — a new
 * push token, a new timezone, a new app version — updates its one row and
 * moves last_seen_at.
 *
 * A phone has one owner at a time. A push token that arrives under a different
 * session than the one holding it — a reinstall, or a second account signing in
 * on the same phone — moves: the Device that held it is deleted and the caller
 * gets a fresh one. The session it left is not touched; only its Device is.
 */
final class DeviceRegistration
{
    public static function register(User $user, PersonalAccessToken $token, DeviceRegistrationData $data): Device
    {
        return DB::transaction(function () use ($user, $token, $data) {
            Device::query()
                ->where('push_token', $data->pushToken)
                ->where('personal_access_token_id', '!=', $token->id)
                ->delete();

            return Device::query()->updateOrCreate(
                ['personal_access_token_id' => $token->id],
                [
                    'user_id' => $user->id,
                    'push_token' => $data->pushToken,
                    'platform' => $data->platform,
                    'timezone' => $data->timezone,
                    'app_version' => $data->appVersion,
                    'build_profile' => $data->buildProfile,
                    'device_name' => $data->deviceName,
                    'last_seen_at' => now(),
                ],
            );
        });
    }
}
