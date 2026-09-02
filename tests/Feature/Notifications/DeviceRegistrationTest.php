<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\NewAccessToken;
use Tests\TestCase;

/**
 * A Device is an authenticated session (ADR-0003): it is bound to the Sanctum
 * token it was registered under and lives exactly as long as that token does.
 *
 * Sanctum::actingAs() hands the request a TransientToken with no row behind it,
 * so these tests mint real tokens and send them as bearers — the same shape the
 * mobile app produces.
 */
class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Send the next request as this session. The auth guard caches the user it
     * resolved for the first request of a test, so switching bearers without
     * forgetting it would keep acting as the previous session.
     */
    private function asSession(NewAccessToken $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token->plainTextToken);
    }

    private const PAYLOAD = [
        'push_token' => 'ExponentPushToken[abcdefghijklmnopqrstuv]',
        'platform' => 'ios',
        'timezone' => 'Europe/Skopje',
        'app_version' => '1.0.5',
        'build_profile' => 'production',
        'device_name' => 'iPhone 15',
    ];

    public function test_registering_creates_a_device_bound_to_the_calling_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $response = $this->asSession($token)
            ->putJson('/api/devices', self::PAYLOAD);

        $response->assertOk()
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.timezone', 'Europe/Skopje');

        $device = Device::sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame($token->accessToken->id, $device->personal_access_token_id);
        $this->assertSame(self::PAYLOAD['push_token'], $device->push_token);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_registering_again_from_the_same_token_updates_its_one_device(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $this->travelTo('2026-09-02 10:00:00');
        $this->asSession($token)->putJson('/api/devices', self::PAYLOAD)->assertOk();

        $this->travelTo('2026-09-03 10:00:00');
        $this->asSession($token)->putJson('/api/devices', [
            ...self::PAYLOAD,
            'push_token' => 'ExponentPushToken[rotated-rotated-rotated]',
            'timezone' => 'Europe/London',
            'app_version' => '1.0.6',
        ])->assertOk();

        $device = Device::sole();
        $this->assertSame('ExponentPushToken[rotated-rotated-rotated]', $device->push_token);
        $this->assertSame('Europe/London', $device->timezone);
        $this->assertSame('1.0.6', $device->app_version);
        $this->assertSame('2026-09-03 10:00:00', $device->last_seen_at->toDateTimeString());
    }

    public function test_a_push_token_reregistered_under_another_session_moves_to_it(): void
    {
        $previousOwner = User::factory()->create();
        $oldToken = $previousOwner->createToken('auth-token');
        $this->asSession($oldToken)->putJson('/api/devices', self::PAYLOAD)->assertOk();

        $newOwner = User::factory()->create();
        $newToken = $newOwner->createToken('auth-token');
        $this->asSession($newToken)->putJson('/api/devices', self::PAYLOAD)->assertOk();

        $device = Device::sole();
        $this->assertSame($newOwner->id, $device->user_id);
        $this->assertSame($newToken->accessToken->id, $device->personal_access_token_id);
        $this->assertDatabaseMissing('devices', ['personal_access_token_id' => $oldToken->accessToken->id]);
        // The previous owner's session itself is untouched — only its Device is gone.
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $oldToken->accessToken->id]);
    }

    public function test_logging_out_ends_the_device(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');
        $this->asSession($token)->putJson('/api/devices', self::PAYLOAD)->assertOk();

        $this->asSession($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_deleting_the_account_ends_every_device(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('auth-token');
        $tablet = $user->createToken('auth-token');
        $this->asSession($phone)->putJson('/api/devices', self::PAYLOAD)->assertOk();
        $this->asSession($tablet)->putJson('/api/devices', [
            ...self::PAYLOAD,
            'push_token' => 'ExponentPushToken[tablet-tablet-tablet-tab]',
            'platform' => 'android',
        ])->assertOk();
        $this->assertDatabaseCount('devices', 2);

        $this->asSession($phone)->deleteJson('/api/user', ['password' => 'password'])->assertNoContent();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_a_stateful_session_cannot_be_a_device(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/devices', self::PAYLOAD)
            ->assertBadRequest();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_registration_rejects_a_malformed_payload(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $this->asSession($token)->putJson('/api/devices', [
            'push_token' => 'not-an-expo-token',
            'platform' => 'windows',
            'timezone' => 'Mars/Olympus_Mons',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['push_token', 'platform', 'timezone']);

        $this->asSession($token)->putJson('/api/devices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['push_token', 'platform']);

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_a_device_may_register_without_the_optional_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $this->asSession($token)->putJson('/api/devices', [
            'push_token' => self::PAYLOAD['push_token'],
            'platform' => 'android',
        ])->assertOk()
            ->assertJsonPath('data.timezone', null);

        $this->assertSame(config('notifications.default_timezone'), Device::sole()->timezone()->getName());
    }

    public function test_a_reinstall_by_the_same_user_moves_the_push_token_to_the_new_session(): void
    {
        $user = User::factory()->create();
        $before = $user->createToken('auth-token');
        $this->asSession($before)->putJson('/api/devices', self::PAYLOAD)->assertOk();

        $after = $user->createToken('auth-token');
        $this->asSession($after)->putJson('/api/devices', self::PAYLOAD)->assertOk();

        $device = Device::sole();
        $this->assertSame($after->accessToken->id, $device->personal_access_token_id);
        $this->assertSame($user->id, $device->user_id);
    }
}
