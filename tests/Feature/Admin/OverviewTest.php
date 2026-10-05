<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PartnerPlan;
use App\Enums\WorkoutSessionStatus;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Services\Admin\Overview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

/**
 * The Admin Overview module (ticket 11): one structure of live counts with
 * known fixtures. "Now" is Wednesday 7 Oct 2026, 12:00; KPIs compare the last
 * 7 days with the 7 days before.
 */
class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_kpis_compare_the_last_7_days_with_the_7_days_before(): void
    {
        // Last 7 days = 30 Sep 12:00 → now; the 7 before = 23 Sep 12:00 → 30 Sep 12:00.
        // Signups: four in the last 7 days, two in the 7 before, two long ago.
        $ada = $this->member('2026-10-05 01:00:00');
        $grace = $this->member('2026-10-06 09:00:00');
        $this->member('2026-10-07 11:00:00');
        $this->member('2026-10-01 10:00:00');
        $linus = $this->member('2026-09-28 09:00:00');
        $this->member('2026-09-30 11:00:00');
        $this->member('2026-08-01 10:00:00');
        $this->member('2026-08-02 10:00:00');

        // Staff never count.
        $admin = $this->userWithRole('admin', ['created_at' => '2026-10-06 10:00:00']);
        $this->userWithRole('partner_admin', ['created_at' => '2026-09-29 10:00:00']);
        $this->workoutSession($admin, WorkoutSessionStatus::Completed, '2026-10-06 10:00:00');

        // Completed Sessions in the last 7 days: Ada three times, Grace once.
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-10-01 18:00:00');
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-10-05 18:00:00');
        $this->workoutSession($ada, WorkoutSessionStatus::Completed, '2026-10-07 08:00:00');
        $this->workoutSession($grace, WorkoutSessionStatus::Completed, '2026-10-06 18:00:00');
        // Not completed: never counts.
        $this->workoutSession($linus, WorkoutSessionStatus::Active, '2026-10-06 18:00:00');
        // The 7 days before: Linus only.
        $this->workoutSession($linus, WorkoutSessionStatus::Completed, '2026-09-29 18:00:00');

        $kpis = Overview::summary()['kpis'];

        $this->assertSame(['current' => 8, 'previous' => 4, 'delta' => 4], $kpis['users']);
        $this->assertSame(['current' => 4, 'previous' => 2, 'delta' => 2], $kpis['signups']);
        $this->assertSame(['current' => 2, 'previous' => 1, 'delta' => 1], $kpis['active']);
        $this->assertSame(['current' => 4, 'previous' => 1, 'delta' => 3], $kpis['completed_sessions']);
    }

    public function test_on_monday_morning_the_kpis_still_cover_a_full_7_days(): void
    {
        $this->travelTo('2026-10-12 09:00:00');

        $this->member('2026-10-08 10:00:00');
        $this->workoutSession($this->member('2026-08-01 10:00:00'), WorkoutSessionStatus::Completed, '2026-10-09 10:00:00');

        $kpis = Overview::summary()['kpis'];

        $this->assertSame(1, $kpis['signups']['current']);
        $this->assertSame(1, $kpis['active']['current']);
        $this->assertSame(1, $kpis['completed_sessions']['current']);
    }

    public function test_the_active_kpi_matches_the_active_users_list(): void
    {
        $this->workoutSession($this->member('2026-08-01 10:00:00'), WorkoutSessionStatus::Completed, '2026-10-01 10:00:00');
        $this->workoutSession($this->member('2026-08-01 10:00:00'), WorkoutSessionStatus::Completed, '2026-09-25 10:00:00');

        $listed = \App\Services\Admin\ActivityStatuses::constrain(User::query()->appUsers(), \App\Enums\ActivityStatus::Active)->count();

        $this->assertSame($listed, Overview::summary()['kpis']['active']['current']);
    }

    public function test_the_funnel_counts_each_stage_for_users_who_signed_up_in_the_last_28_days(): void
    {
        // Trained on day 1 and again on day 8: every stage.
        $all = $this->member('2026-09-20 10:00:00');
        $this->workoutSession($all, WorkoutSessionStatus::Completed, '2026-09-21 10:00:00');
        $this->workoutSession($all, WorkoutSessionStatus::Completed, '2026-09-28 10:00:00');
        // First Completed Session, nothing in week two.
        $once = $this->member('2026-09-20 10:00:00');
        $this->workoutSession($once, WorkoutSessionStatus::Completed, '2026-09-21 10:00:00');
        // Onboarded, never trained.
        $this->member('2026-09-25 10:00:00');
        // Verified, not onboarded.
        $this->member('2026-09-25 10:00:00', ['onboarding_completed_at' => null]);
        // Unverified, not onboarded.
        $this->member('2026-10-06 10:00:00', ['email_verified_at' => null, 'onboarding_completed_at' => null]);
        // Unverified yet onboarded and training in week two: stages count on their own.
        $unverified = $this->member('2026-09-15 10:00:00', ['email_verified_at' => null]);
        $this->workoutSession($unverified, WorkoutSessionStatus::Completed, '2026-09-24 10:00:00');
        // Only a session that never completed.
        $unfinishedSession = $this->member('2026-09-15 10:00:00');
        $this->workoutSession($unfinishedSession, WorkoutSessionStatus::Active, '2026-09-24 10:00:00');
        // First Completed Session exactly 14 days in: that is week three.
        $dayFourteen = $this->member('2026-09-15 10:00:00');
        $this->workoutSession($dayFourteen, WorkoutSessionStatus::Completed, '2026-09-29 10:00:00');

        // Outside the 28 days, and staff: never counted.
        $old = $this->member('2026-09-09 11:00:00');
        $this->workoutSession($old, WorkoutSessionStatus::Completed, '2026-09-17 10:00:00');
        $this->userWithRole('admin', ['created_at' => '2026-10-01 10:00:00']);

        $this->assertSame([
            'signed_up' => 8,
            'verified' => 6,
            'onboarded' => 6,
            'first_completed_session' => 4,
            'trained_in_week_two' => 2,
        ], Overview::summary()['funnel']);
    }

    public function test_the_paywall_card_counts_users_whose_access_source_is_none(): void
    {
        $sponsor = Partner::factory()->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => null]);
        $free = Partner::factory()->create(['plan' => PartnerPlan::Free]);

        $this->member('2026-08-01 10:00:00', ['partner_id' => $free->id]);
        $this->member('2026-08-01 10:00:00', ['partner_id' => $free->id, 'grace_period_ends_at' => '2026-07-01 00:00:00']);
        $this->member('2026-08-01 10:00:00', ['partner_id' => $free->id, 'grace_period_ends_at' => '2026-12-01 00:00:00']);
        $this->member('2026-08-01 10:00:00', ['partner_id' => $sponsor->id]);
        $subscribed = $this->member('2026-08-01 10:00:00', ['partner_id' => $free->id]);
        Subscription::factory()->create(['user_id' => $subscribed->id]);
        $this->member('2026-08-01 10:00:00', ['partner_id' => $free->id, 'deleted_at' => '2026-09-01 00:00:00']);
        $this->userWithRole('admin');

        config(['subscriptions.enforced' => false]);
        $this->assertSame(['enforced' => false, 'none' => 2], Overview::summary()['paywall']);

        Cache::flush();
        config(['subscriptions.enforced' => true]);
        $this->assertSame(['enforced' => true, 'none' => 2], Overview::summary()['paywall']);
    }

    public function test_needs_attention_counts_each_problem(): void
    {
        $this->failJob();
        $this->failJob();
        $this->webhookCall(['code' => 0, 'message' => 'No user', 'trace' => '']);
        $this->webhookCall(null);

        // Unfinished Accounts: unverified, or verified and not onboarded.
        $this->member('2026-10-01 10:00:00', ['email_verified_at' => null]);
        $this->member('2026-10-01 10:00:00', ['onboarding_completed_at' => null]);
        $this->member('2026-10-01 10:00:00', ['onboarding_completed_at' => null, 'deleted_at' => '2026-10-02 00:00:00']);
        $this->userWithRole('partner_admin', ['onboarding_completed_at' => null]);

        // Stuck: active for more than 24 hours. Counted per user.
        $stuck = $this->member('2026-08-01 10:00:00');
        $this->activeSession($stuck, '2026-10-06 11:00:00');
        $this->activeSession($stuck, '2026-10-01 11:00:00');
        $this->activeSession($this->member('2026-08-01 10:00:00'), '2026-10-06 13:00:00');

        // Sponsorships running out within 30 days.
        Partner::factory()->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-10-17 00:00:00']);
        Partner::factory()->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-11-06 11:00:00']);
        Partner::factory()->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-11-06 13:00:00']);
        Partner::factory()->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-10-06 00:00:00']);
        Partner::factory()->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => null]);
        Partner::factory()->create(['plan' => PartnerPlan::Free, 'plan_expires_at' => '2026-10-10 00:00:00']);

        $this->assertSame([
            'failed_jobs' => 2,
            'failed_webhooks' => 1,
            'unfinished_accounts' => 2,
            'stuck_sessions' => 1,
            'expiring_sponsorships' => 2,
        ], Overview::summary()['attention']);
    }

    public function test_the_summary_is_cached_for_ten_minutes(): void
    {
        $this->member('2026-10-06 10:00:00');
        $this->assertSame(1, Overview::summary()['kpis']['signups']['current']);

        $this->member('2026-10-06 11:00:00');
        $this->travel(9)->minutes();
        $this->assertSame(1, Overview::summary()['kpis']['signups']['current']);

        $this->travel(2)->minutes();
        $this->assertSame(2, Overview::summary()['kpis']['signups']['current']);
    }

    private function activeSession(User $user, string $startedAt): void
    {
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => WorkoutSessionStatus::Active,
            'performed_at' => $startedAt,
            'completed_at' => null,
        ]);
    }

    private function failJob(): void
    {
        app('queue.failer')->log('database', 'default', json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'SomeJob']), new RuntimeException('boom'));
    }

    /**
     * @param  array<string, mixed>|null  $exception
     */
    private function webhookCall(?array $exception): void
    {
        WebhookCall::create([
            'name' => 'revenuecat',
            'url' => '/api/webhooks/revenuecat',
            'payload' => ['event' => ['type' => 'RENEWAL']],
            'exception' => $exception,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(string $signedUpAt, array $attributes = []): User
    {
        return User::factory()->create([
            'created_at' => $signedUpAt,
            'onboarding_completed_at' => $signedUpAt,
            ...$attributes,
        ]);
    }

    private function workoutSession(User $user, WorkoutSessionStatus $status, string $at): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => $status,
            'performed_at' => Carbon::parse($at)->subHour(),
            'completed_at' => $status === WorkoutSessionStatus::Completed ? $at : null,
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
