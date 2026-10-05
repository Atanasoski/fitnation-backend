<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AccessSource;
use App\Enums\PartnerPlan;
use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionStore;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\AccessSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Access Source agreement test: for every fixture, the per-user answer is
 * the expected source, and the query constraint for each source returns
 * exactly the users carrying it. Fixtures sit on each side of every expiry —
 * subscriptions, sponsorships and Complimentary Access — and cover precedence.
 */
class AccessSourceTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    /**
     * fixture name => [expected source, subscription state or null, partner state or null, Complimentary until (minutes from now) or null]
     *
     * @return array<string, array{AccessSource, ?array<string, mixed>, ?array<string, mixed>, ?int}>
     */
    private function fixtures(): array
    {
        $sub = fn (SubscriptionStatus $status, int $expiresInMinutes, SubscriptionPeriodType $period = SubscriptionPeriodType::Normal) => [
            'status' => $status,
            'period_type' => $period,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ];
        $sponsor = fn (?int $expiresInMinutes) => [
            'plan' => PartnerPlan::Sponsor,
            'plan_expires_at' => $expiresInMinutes === null ? null : now()->addMinutes($expiresInMinutes),
        ];
        $free = ['plan' => PartnerPlan::Free, 'plan_expires_at' => null];

        return [
            'nothing at all' => [AccessSource::None, null, null, null],
            'on a free partner' => [AccessSource::None, null, $free, null],
            'active, a minute ahead' => [AccessSource::Subscribed, $sub(SubscriptionStatus::Active, 1), null, null],
            'active, a minute past' => [AccessSource::None, $sub(SubscriptionStatus::Active, -1), null, null],
            'active intro offer' => [AccessSource::Subscribed, $sub(SubscriptionStatus::Active, 600, SubscriptionPeriodType::Intro), null, null],
            'active, no expiry recorded' => [AccessSource::None, ['status' => SubscriptionStatus::Active, 'expires_at' => null], null, null],
            'trial, a minute ahead' => [AccessSource::Trial, $sub(SubscriptionStatus::Active, 1, SubscriptionPeriodType::Trial), null, null],
            'trial, a minute past' => [AccessSource::None, $sub(SubscriptionStatus::Active, -1, SubscriptionPeriodType::Trial), null, null],
            'cancelled, a minute ahead' => [AccessSource::Cancelled, $sub(SubscriptionStatus::Cancelled, 1), null, null],
            'cancelled, a minute past' => [AccessSource::None, $sub(SubscriptionStatus::Cancelled, -1), null, null],
            'cancelled during trial, ahead' => [AccessSource::Cancelled, $sub(SubscriptionStatus::Cancelled, 600, SubscriptionPeriodType::Trial), null, null],
            'billing issue, a minute ahead' => [AccessSource::BillingIssue, $sub(SubscriptionStatus::BillingIssue, 1), null, null],
            'billing issue, a minute past' => [AccessSource::None, $sub(SubscriptionStatus::BillingIssue, -1), null, null],
            'paused, a minute ahead' => [AccessSource::Paused, $sub(SubscriptionStatus::Paused, 1), null, null],
            'paused, a minute past' => [AccessSource::None, $sub(SubscriptionStatus::Paused, -1), null, null],
            'expired status, expiry still ahead' => [AccessSource::None, $sub(SubscriptionStatus::Expired, 600), null, null],
            'sponsor, no end date' => [AccessSource::Sponsored, null, $sponsor(null), null],
            'sponsor, a minute ahead' => [AccessSource::Sponsored, null, $sponsor(1), null],
            'sponsor, a minute past' => [AccessSource::None, null, $sponsor(-1), null],
            'complimentary, a minute ahead' => [AccessSource::Complimentary, null, null, 1],
            'complimentary, a minute past' => [AccessSource::None, null, null, -1],
            'sponsored with an active subscription' => [AccessSource::Subscribed, $sub(SubscriptionStatus::Active, 600), $sponsor(null), null],
            'complimentary with an active subscription' => [AccessSource::Subscribed, $sub(SubscriptionStatus::Active, 600), null, 600],
            'complimentary with a billing issue' => [AccessSource::BillingIssue, $sub(SubscriptionStatus::BillingIssue, 600), null, 600],
            'sponsored and complimentary' => [AccessSource::Sponsored, null, $sponsor(600), 600],
            'lapsed subscription, sponsored' => [AccessSource::Sponsored, $sub(SubscriptionStatus::Cancelled, -1), $sponsor(null), null],
            'lapsed subscription and sponsorship, complimentary' => [AccessSource::Complimentary, $sub(SubscriptionStatus::Active, -1), $sponsor(-1), 1],
        ];
    }

    public function test_the_source_for_a_user_and_the_query_constraint_agree_on_every_fixture(): void
    {
        $this->travelTo(self::NOW);
        $expected = $this->createFixtures();

        foreach ($expected as $name => [$id, $source]) {
            $this->assertSame($source, AccessSources::for(User::findOrFail($id))->source, "per-user source for: {$name}");
        }

        $batch = AccessSources::forUsers(User::query()->whereKey(array_column($expected, 0))->get());
        foreach ($expected as $name => [$id, $source]) {
            $this->assertSame($source, $batch[$id]->source, "batch source for: {$name}");
        }

        $ids = array_column($expected, 0);
        foreach (AccessSource::cases() as $source) {
            $want = collect($expected)->filter(fn ($row) => $row[1] === $source)->map(fn ($row) => $row[0])->sort()->values()->all();
            $got = AccessSources::constrain(User::query()->whereKey($ids), $source)->pluck('id')->sort()->values()->all();

            $this->assertSame($want, $got, "constraint for {$source->value}");
        }
    }

    public function test_the_source_is_the_same_whether_or_not_subscriptions_are_enforced(): void
    {
        $this->travelTo(self::NOW);
        $expected = $this->createFixtures();
        $ids = array_column($expected, 0);

        $read = function () use ($ids) {
            $users = User::query()->whereKey($ids)->get();
            $perUser = AccessSources::forUsers($users)->map(fn ($access) => $access->source->label())->all();
            $constrained = collect(AccessSource::cases())->mapWithKeys(fn (AccessSource $source) => [
                $source->value => AccessSources::constrain(User::query()->whereKey($ids), $source)->pluck('id')->sort()->values()->all(),
            ])->all();

            return [$perUser, $constrained];
        };

        config(['subscriptions.enforced' => true]);
        $enforced = $read();
        config(['subscriptions.enforced' => false]);
        $notEnforced = $read();

        $this->assertSame($enforced, $notEnforced);
    }

    public function test_the_source_moves_with_the_clock(): void
    {
        $this->travelTo(self::NOW);
        $user = User::factory()->create(['grace_period_ends_at' => now()->addHour()]);

        $this->assertSame(AccessSource::Complimentary, AccessSources::for($user)->source);

        $this->travel(2)->hours();

        $this->assertSame(AccessSource::None, AccessSources::for($user)->source);
        $this->assertSame([$user->id], AccessSources::constrain(User::query()->whereKey($user->id), AccessSource::None)->pluck('id')->all());
    }

    public function test_a_subscribed_user_carries_product_period_store_and_renewal(): void
    {
        $this->travelTo(self::NOW);
        $user = User::factory()->create();
        Subscription::factory()->yearly()->create([
            'user_id' => $user->id,
            'product_id' => 'com.fitnation.app.premium.yearly:yearly',
            'store' => SubscriptionStore::PlayStore,
            'expires_at' => Carbon::parse('2027-03-12 09:00:00'),
        ]);

        $access = AccessSources::for($user);

        $this->assertSame(AccessSource::Subscribed, $access->source);
        $this->assertSame('com.fitnation.app.premium.yearly:yearly', $access->productId);
        $this->assertSame('Yearly', $access->period);
        $this->assertSame(SubscriptionStore::PlayStore, $access->store);
        $this->assertEquals(Carbon::parse('2027-03-12 09:00:00'), $access->until);
        $this->assertSame('Yearly · Google Play · renews 12 Mar 2027', $access->detail());
    }

    public function test_a_cancelled_user_carries_when_they_cancelled_and_until_when_they_paid(): void
    {
        $this->travelTo(self::NOW);
        $user = User::factory()->create();
        Subscription::factory()->cancelled()->create([
            'user_id' => $user->id,
            'expires_at' => Carbon::parse('2026-11-03 09:00:00'),
        ]);

        $access = AccessSources::for($user);

        $this->assertSame(AccessSource::Cancelled, $access->source);
        $this->assertSame('Monthly', $access->period);
        $this->assertEquals(Carbon::parse('2026-10-04 12:00:00'), $access->cancelledAt);
        $this->assertSame('Monthly · App Store · cancelled 4 Oct 2026 · paid until 3 Nov 2026', $access->detail());
    }

    public function test_a_sponsored_user_carries_the_sponsoring_partner_and_its_end_date(): void
    {
        $this->travelTo(self::NOW);
        $gym = Partner::factory()->sponsor()->create(['name' => 'Iron Temple', 'plan_expires_at' => Carbon::parse('2027-01-31 00:00:00')]);
        $user = User::factory()->create(['partner_id' => $gym->id]);

        $access = AccessSources::for($user);

        $this->assertSame(AccessSource::Sponsored, $access->source);
        $this->assertTrue($access->sponsor->is($gym));
        $this->assertEquals(Carbon::parse('2027-01-31 00:00:00'), $access->until);
        $this->assertSame('Iron Temple pays · sponsorship until 31 Jan 2027', $access->detail());
    }

    public function test_a_complimentary_user_carries_the_end_date(): void
    {
        $this->travelTo(self::NOW);
        $user = User::factory()->create(['grace_period_ends_at' => Carbon::parse('2026-12-01 00:00:00')]);

        $access = AccessSources::for($user);

        $this->assertSame(AccessSource::Complimentary, $access->source);
        $this->assertEquals(Carbon::parse('2026-12-01 00:00:00'), $access->until);
        $this->assertSame('Complimentary until 1 Dec 2026', $access->detail());
    }

    public function test_a_user_with_no_access_says_so(): void
    {
        $this->travelTo(self::NOW);
        $user = User::factory()->create();

        $access = AccessSources::for($user);

        $this->assertSame(AccessSource::None, $access->source);
        $this->assertNull($access->until);
        $this->assertSame('No subscription, no sponsor, no Complimentary Access', $access->detail());
    }

    /**
     * @return array<string, array{int, AccessSource}>
     */
    private function createFixtures(): array
    {
        $expected = [];
        foreach ($this->fixtures() as $name => [$source, $subscription, $partner, $complimentaryMinutes]) {
            $user = User::factory()->create([
                'partner_id' => $partner === null ? null : Partner::factory()->create($partner)->id,
                'grace_period_ends_at' => $complimentaryMinutes === null ? null : now()->addMinutes($complimentaryMinutes),
            ]);
            if ($subscription !== null) {
                Subscription::factory()->create(['user_id' => $user->id, ...$subscription]);
            }
            $expected[$name] = [$user->id, $source];
        }

        return $expected;
    }
}
