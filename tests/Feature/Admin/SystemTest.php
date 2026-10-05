<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\FetchExpoReceipts;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Webhooks\RevenueCat\ProcessRevenueCatWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

class SystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_system_page_says_so_when_nothing_failed(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/system')
            ->assertOk()
            ->assertSee('No failed jobs')
            ->assertSee('No failed webhooks');
    }

    public function test_failed_jobs_are_listed_newest_first_with_queue_and_the_first_line_of_the_error(): void
    {
        $this->travelTo('2026-10-01 09:00:00');
        $this->failJob('notifications', "Expo said no\n#0 /app/Jobs/FetchExpoReceipts.php(42)");
        $this->travelTo('2026-10-03 09:00:00');
        $this->failJob('default', "Connection refused\n#0 trace");

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/system')
            ->assertOk()
            ->assertSee('FetchExpoReceipts')
            ->assertSee('notifications')
            ->assertSeeInOrder(['Connection refused', 'Expo said no'])
            ->assertDontSee('/app/Jobs/FetchExpoReceipts.php(42)');
    }

    public function test_retry_puts_the_failed_job_back_on_the_queue(): void
    {
        $id = $this->failJob('notifications', 'Expo said no');
        Queue::fake();

        $this->actingAs($this->userWithRole('admin'))
            ->post("/admin/system/failed-jobs/{$id}/retry")
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $this->assertCount(1, Queue::pushedRaw());
        $this->assertSame('notifications', Queue::rawPushes()[0]['queue']);
        $this->assertNull(app('queue.failer')->find($id));
    }

    public function test_delete_removes_the_failed_job_without_retrying_it(): void
    {
        $id = $this->failJob('default', 'Boom');
        Queue::fake();

        $this->actingAs($this->userWithRole('admin'))
            ->delete("/admin/system/failed-jobs/{$id}")
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $this->assertNull(app('queue.failer')->find($id));
        $this->assertCount(0, Queue::pushedRaw());
    }

    public function test_an_unknown_failed_job_is_a_404(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/system/failed-jobs/'.Str::uuid().'/retry')
            ->assertNotFound();
    }

    public function test_failed_webhook_calls_are_listed_and_succeeded_ones_are_not(): void
    {
        $this->failedWebhook('INITIAL_PURCHASE', 'No user for app_user_id 42');
        WebhookCall::create([
            'name' => 'revenuecat',
            'url' => '/api/webhooks/revenuecat',
            'payload' => ['event' => ['type' => 'RENEWAL']],
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/system')
            ->assertOk()
            ->assertSee('INITIAL_PURCHASE')
            ->assertSee('No user for app_user_id 42')
            ->assertDontSee('RENEWAL');
    }

    public function test_replay_clears_the_exception_and_dispatches_the_processing_job(): void
    {
        Queue::fake();
        $call = $this->failedWebhook('INITIAL_PURCHASE', 'No user');
        $other = $this->failedWebhook('RENEWAL', 'No user');

        $this->actingAs($this->userWithRole('admin'))
            ->post("/admin/system/webhooks/{$call->id}/replay")
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $this->assertNull($call->fresh()->exception);
        $this->assertNotNull($other->fresh()->exception);
        Queue::assertPushed(ProcessRevenueCatWebhook::class, 1);
        Queue::assertPushed(ProcessRevenueCatWebhook::class, fn ($job) => $job->webhookCall->is($call));
    }

    public function test_replay_all_replays_every_failed_call_whatever_its_age(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-05 12:00:00');
        $recent = $this->failedWebhook('INITIAL_PURCHASE', 'No user');
        $old = $this->failedWebhook('RENEWAL', 'No user');
        $old->forceFill(['created_at' => now()->subDays(60)])->save();

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/system/webhooks/replay')
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $this->assertNull($recent->fresh()->exception);
        $this->assertNull($old->fresh()->exception);
        Queue::assertPushed(ProcessRevenueCatWebhook::class, 2);
    }

    public function test_a_webhook_call_that_has_not_failed_cannot_be_replayed(): void
    {
        Queue::fake();
        $call = WebhookCall::create([
            'name' => 'revenuecat',
            'url' => '/api/webhooks/revenuecat',
            'payload' => ['event' => ['type' => 'RENEWAL']],
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->post("/admin/system/webhooks/{$call->id}/replay")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function systemRequests(): array
    {
        return [
            'page' => ['get', '/admin/system'],
            'retry job' => ['post', '/admin/system/failed-jobs/{job}/retry'],
            'delete job' => ['delete', '/admin/system/failed-jobs/{job}'],
            'replay webhook' => ['post', '/admin/system/webhooks/{call}/replay'],
            'replay all webhooks' => ['post', '/admin/system/webhooks/replay'],
        ];
    }

    #[DataProvider('systemRequests')]
    public function test_partner_admins_and_users_get_a_403(string $method, string $path): void
    {
        Queue::fake();
        $jobId = $this->failJob('default', 'Boom');
        $call = $this->failedWebhook('INITIAL_PURCHASE', 'No user');
        $path = str_replace(['{job}', '{call}'], [$jobId, (string) $call->id], $path);

        $partnerAdmin = $this->userWithRole('partner_admin', ['partner_id' => Partner::factory()->create()->id]);
        $this->actingAs($partnerAdmin)->{$method}($path)->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->{$method}($path)->assertForbidden();

        $this->assertNotNull(app('queue.failer')->find($jobId));
        $this->assertNotNull($call->fresh()->exception);
        Queue::assertNothingPushed();
    }

    private function failJob(string $queue, string $error): string
    {
        $uuid = (string) Str::uuid();
        $payload = json_encode([
            'uuid' => $uuid,
            'displayName' => FetchExpoReceipts::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'attempts' => 3,
            'data' => [
                'commandName' => FetchExpoReceipts::class,
                'command' => serialize(new FetchExpoReceipts),
            ],
        ]);

        app('queue.failer')->log('database', $queue, $payload, new RuntimeException($error));

        return $uuid;
    }

    private function failedWebhook(string $type, string $message): WebhookCall
    {
        return WebhookCall::create([
            'name' => 'revenuecat',
            'url' => '/api/webhooks/revenuecat',
            'payload' => ['event' => ['type' => $type]],
            'exception' => ['code' => 0, 'message' => $message, 'trace' => '#0 trace'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
