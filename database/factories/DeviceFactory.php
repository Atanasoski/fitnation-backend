<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * A Device is bound to a Sanctum token (ADR-0003), so the factory mints one
     * for the user it creates unless told otherwise.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'personal_access_token_id' => function (array $attributes) {
                return User::find($attributes['user_id'])->createToken('auth-token')->accessToken->id;
            },
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
            'platform' => fake()->randomElement(['ios', 'android']),
            'timezone' => 'Europe/Skopje',
            'app_version' => '1.0.5',
            'build_profile' => 'production',
            'device_name' => fake()->randomElement(['iPhone 15', 'Pixel 8', 'Galaxy S24']),
            'last_seen_at' => now(),
        ];
    }
}
