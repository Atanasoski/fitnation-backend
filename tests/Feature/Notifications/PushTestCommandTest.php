<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\User;
use App\Notifications\TestPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTestCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://exp.host/--/api/v2/push/send';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.enabled', true);
        config()->set('notifications.expo.access_token', 'expo-secret');
    }

    public function test_it_pushes_to_every_device_of_the_user_and_reports_the_tickets(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => [
            ['status' => 'ok', 'id' => 'ticket-a'],
            ['status' => 'ok', 'id' => 'ticket-b'],
        ]])]);

        $user = User::factory()->create(['email' => 'athlete@example.com']);
        Device::factory()->for($user)->count(2)->create();

        $this->artisan('push:test', ['user' => 'athlete@example.com', '--title' => 'Ping', '--body' => 'From artisan'])
            ->expectsOutputToContain('ticket-a')
            ->expectsOutputToContain('ticket-b')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->data()[0]['title'] === 'Ping'
            && $request->data()[0]['body'] === 'From artisan');
        $this->assertSame(TestPush::class, $user->notifications()->sole()->type);
    }

    public function test_it_accepts_a_user_id_and_has_default_copy(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);

        $user = User::factory()->create();
        Device::factory()->for($user)->create();

        $this->artisan('push:test', ['user' => (string) $user->id])->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_it_fails_clearly_for_an_unknown_user_or_one_with_no_devices(): void
    {
        Http::fake();

        $this->artisan('push:test', ['user' => 'nobody@example.com'])->assertFailed();

        $user = User::factory()->create();
        $this->artisan('push:test', ['user' => (string) $user->id])
            ->expectsOutputToContain('no Devices')
            ->assertFailed();

        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }
}
