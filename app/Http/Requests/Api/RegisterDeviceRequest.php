<?php

namespace App\Http\Requests\Api;

use App\Services\Notifications\DeviceRegistrationData;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'push_token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[\w-]+\]$/'],
            'platform' => ['required', 'string', 'in:ios,android'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'build_profile' => ['nullable', 'string', 'max:32'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): DeviceRegistrationData
    {
        $validated = $this->validated();

        return new DeviceRegistrationData(
            pushToken: $validated['push_token'],
            platform: $validated['platform'],
            timezone: $validated['timezone'] ?? null,
            appVersion: $validated['app_version'] ?? null,
            buildProfile: $validated['build_profile'] ?? null,
            deviceName: $validated['device_name'] ?? null,
        );
    }
}
