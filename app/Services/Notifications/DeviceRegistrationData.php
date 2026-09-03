<?php

namespace App\Services\Notifications;

/**
 * What a Device reports about itself when it registers. Validated by the form
 * request that builds it; carried here so DeviceRegistration takes one typed
 * argument rather than a request.
 */
final readonly class DeviceRegistrationData
{
    public function __construct(
        public string $pushToken,
        public string $platform,
        public ?string $timezone = null,
        public ?string $appVersion = null,
        public ?string $buildProfile = null,
        public ?string $deviceName = null,
    ) {}
}
