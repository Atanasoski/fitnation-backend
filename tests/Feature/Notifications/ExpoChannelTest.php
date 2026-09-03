<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\User;
use App\Notifications\TestPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The seam every push goes through. Expo is faked at the HTTP boundary; nothing
 * here reaches the network.
 */
class ExpoChannelTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://exp.host/--/api/v2/push/send';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.enabled', true);
        config()->set('notifications.expo.access_token', 'expo-secret');
        config()->set('notifications.only_build_profile', null);
    }

    public function test_a_notification_is_recorded_once_and_pushed_to_the_users_device(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);

        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create(['push_token' => 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]']);

        $user->notify(new TestPush('Hello', 'From the test suite'));

        $this->assertDatabaseCount('notifications', 1);
        $row = $user->notifications()->sole();
        $this->assertSame(TestPush::class, $row->type);
        $this->assertSame([$device->id => 'ticket-1'], $row->data['expo_tickets']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $message = $request->data()[0];

            return $request->url() === self::SEND_URL
                && $request->hasHeader('Authorization', 'Bearer expo-secret')
                && $message['to'] === 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]'
                && $message['title'] === 'Hello'
                && $message['body'] === 'From the test suite'
                && $message['channelId'] === 'default';
        });
    }

    public function test_devices_are_pushed_in_requests_of_at_most_one_hundred(): void
    {
        Http::fake(function (Request $request) {
            $count = count($request->data());

            return Http::response(['data' => array_map(
                fn (int $i) => ['status' => 'ok', 'id' => "ticket-{$i}"],
                range(1, $count),
            )]);
        });

        $user = User::factory()->create();
        Device::factory()->for($user)->count(250)->create();

        $user->notify(new TestPush('Hello', 'Everyone'));

        Http::assertSentCount(3);
        $sizes = [];
        Http::assertSent(function (Request $request) use (&$sizes) {
            $sizes[] = count($request->data());

            return true;
        });
        $this->assertSame([100, 100, 50], $sizes);
        $this->assertCount(250, $user->notifications()->sole()->data['expo_tickets']);
    }

    public function test_the_push_switch_off_records_the_notification_but_pushes_nothing(): void
    {
        Http::fake();

        $user = User::factory()->create(['push_enabled' => false]);
        Device::factory()->for($user)->create();

        $user->notify(new TestPush('Hello', 'Nobody'));

        $this->assertDatabaseCount('notifications', 1);
        $this->assertArrayNotHasKey('expo_tickets', $user->notifications()->sole()->data);
        Http::assertNothingSent();
    }

    public function test_with_notifications_disabled_nothing_is_sent_and_one_line_is_logged(): void
    {
        config()->set('notifications.enabled', false);
        Http::fake();
        Log::shouldReceive('info')->once()->withArgs(
            fn (string $message) => str_contains($message, 'suppressed')
        );

        $user = User::factory()->create();
        Device::factory()->for($user)->create();

        $user->notify(new TestPush('Hello', 'Suppressed'));

        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_production_pushes_only_to_production_devices(): void
    {
        config()->set('notifications.only_build_profile', 'production');
        Http::fake([self::SEND_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-prod']]])]);

        $user = User::factory()->create();
        Device::factory()->for($user)->create(['build_profile' => 'development', 'push_token' => 'ExponentPushToken[dev-dev-dev-dev-dev-dev]']);
        $production = Device::factory()->for($user)->create(['build_profile' => 'production', 'push_token' => 'ExponentPushToken[prod-prod-prod-prod-pro]']);

        $user->notify(new TestPush('Hello', 'Production'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => count($request->data()) === 1
            && $request->data()[0]['to'] === 'ExponentPushToken[prod-prod-prod-prod-pro]');
        $this->assertSame([$production->id => 'ticket-prod'], $user->notifications()->sole()->data['expo_tickets']);
    }

    public function test_a_ticket_rejected_as_not_registered_ends_the_device_but_not_the_session(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => [
            ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
            ['status' => 'ok', 'id' => 'ticket-2'],
        ]])]);

        $user = User::factory()->create();
        $dead = Device::factory()->for($user)->create();
        $alive = Device::factory()->for($user)->create();

        $user->notify(new TestPush('Hello', 'Half of you'));

        $this->assertDatabaseMissing('devices', ['id' => $dead->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $dead->personal_access_token_id]);
        $this->assertSame([$alive->id => 'ticket-2'], $user->notifications()->sole()->data['expo_tickets']);
    }

    public function test_any_other_ticket_error_is_logged_and_keeps_the_device(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => [
            ['status' => 'error', 'message' => 'too big', 'details' => ['error' => 'MessageTooBig']],
        ]])]);
        Log::shouldReceive('warning')->once();

        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create();

        $user->notify(new TestPush('Hello', str_repeat('x', 10)));

        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertArrayNotHasKey('expo_tickets', $user->notifications()->sole()->data);
    }
}
