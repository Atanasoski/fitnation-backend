<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Http\Resources\Api\DeviceResource;
use App\Services\Notifications\DeviceRegistration;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceController extends Controller
{
    /**
     * Register, or re-register, the calling session as a Device.
     *
     * Idempotent for one token: the mobile app calls this on every launch and
     * foreground it deems worth reporting, and always gets its one Device back.
     * There is deliberately no DELETE — revoking the token ends the Device
     * (ADR-0003).
     */
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // A stateful (cookie) session has a TransientToken with no row to bind
        // to. Only token-authenticated sessions can be Devices.
        abort_unless($token instanceof PersonalAccessToken, 400, 'Device registration requires token authentication.');

        $device = DeviceRegistration::register($request->user(), $token, $request->toData());

        return response()->json(['data' => new DeviceResource($device)]);
    }
}
