<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\User;
use App\Notifications\UnfinishedAccountNudge;
use App\Services\Notifications\UnfinishedAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Unfinished Account rule (CONTEXT.md): who is due an email, evaluated at
 * one instant. Instants are written in UTC with the zone stated, because the
 * app timezone is Europe/Skopje and a bare timestamp would be read in it.
 * Skopje is UTC+2 in September, so 10:00 there is 08:00 UTC.
 */
class UnfinishedAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const SKOPJE_TEN_AM = '2026-09-05 08:00:00 UTC';

    private static function at(string $instant): CarbonImmutable
    {
        return CarbonImmutable::parse($instant);
    }

    /** Someone who registered at the given instant and has not finished onboarding. */
    private function registered(string $at, array $attributes = []): User
    {
        return User::factory()->create([
            'created_at' => self::at($at),
            'onboarding_completed_at' => null,
            ...$attributes,
        ]);
    }

    public function test_one_day_after_registering_unverified_is_due_step_one_as_unverified(): void
    {
        $user = $this->registered('2026-09-04 09:30:00 UTC', ['email_verified_at' => null]);

        $due = UnfinishedAccounts::dueAt(self::at(self::SKOPJE_TEN_AM));

        $this->assertCount(1, $due);
        $this->assertTrue($due->first()->user->is($user));
        $this->assertSame(1, $due->first()->step);
        $this->assertSame('unverified', $due->first()->stuckAt);
    }

    /** Record that a step was sent at a given instant, as the command would have. */
    private function nudged(User $user, int $step, string $at): void
    {
        $this->travelTo(self::at($at));
        $user->notify(new UnfinishedAccountNudge($step, 'unverified'));
        $this->travelBack();
    }

    /** @return list<array{int, string}> [step, stuckAt] due, in user order */
    private function dueAt(string $instant): array
    {
        return UnfinishedAccounts::dueAt(self::at($instant))->map(fn ($c) => [$c->step, $c->stuckAt])->all();
    }

    public function test_verified_but_not_onboarded_is_due_step_one_as_not_onboarded(): void
    {
        $this->registered('2026-09-04 09:30:00 UTC', ['email_verified_at' => '2026-09-04 09:35:00']);

        $this->assertSame([[1, 'not_onboarded']], $this->dueAt(self::SKOPJE_TEN_AM));
    }

    public function test_a_social_sign_in_is_created_verified_and_is_never_unverified(): void
    {
        $this->registered('2026-09-04 09:30:00 UTC', [
            'email_verified_at' => '2026-09-04 09:30:00',
            'social_provider' => 'google',
            'social_provider_id' => 'sub-123',
            'password' => null,
        ]);

        $this->assertSame([[1, 'not_onboarded']], $this->dueAt(self::SKOPJE_TEN_AM));
    }

    public function test_the_ladder_is_one_then_three_then_seven_and_then_silence(): void
    {
        $user = $this->registered('2026-09-01 09:30:00 UTC', ['email_verified_at' => null]);

        $this->assertSame([], $this->dueAt('2026-09-01 08:00:00 UTC'), 'day 0');

        $this->assertSame([[1, 'unverified']], $this->dueAt('2026-09-02 08:00:00 UTC'), 'day 1');
        $this->nudged($user, 1, '2026-09-02 08:00:00 UTC');

        $this->assertSame([], $this->dueAt('2026-09-03 08:00:00 UTC'), 'day 2');

        $this->assertSame([[3, 'unverified']], $this->dueAt('2026-09-04 08:00:00 UTC'), 'day 3');
        $this->nudged($user, 3, '2026-09-04 08:00:00 UTC');

        $this->assertSame([], $this->dueAt('2026-09-06 08:00:00 UTC'), 'day 5');

        $this->assertSame([[7, 'unverified']], $this->dueAt('2026-09-08 08:00:00 UTC'), 'day 7');
        $this->nudged($user, 7, '2026-09-08 08:00:00 UTC');

        $this->assertSame([], $this->dueAt('2026-09-09 08:00:00 UTC'), 'day 8');
        $this->assertSame([], $this->dueAt('2026-10-01 08:00:00 UTC'), 'day 30');
    }

    public function test_every_run_within_the_hour_finds_the_same_user_due_until_they_are_nudged(): void
    {
        $user = $this->registered('2026-09-04 09:30:00 UTC');

        $this->assertCount(1, $this->dueAt('2026-09-05 08:00:00 UTC'));
        $this->assertCount(1, $this->dueAt('2026-09-05 08:45:00 UTC'));
        $this->assertSame([], $this->dueAt('2026-09-05 07:45:00 UTC'));
        $this->assertSame([], $this->dueAt('2026-09-05 09:00:00 UTC'));

        $this->nudged($user, 1, '2026-09-05 08:02:00 UTC');
        $this->assertSame([], $this->dueAt('2026-09-05 08:45:00 UTC'));
    }

    public function test_verifying_between_steps_changes_what_the_next_step_says(): void
    {
        $user = $this->registered('2026-09-01 09:30:00 UTC', ['email_verified_at' => null]);
        $this->nudged($user, 1, '2026-09-02 08:00:00 UTC');

        $user->forceFill(['email_verified_at' => '2026-09-03 12:00:00'])->save();

        $this->assertSame([[3, 'not_onboarded']], $this->dueAt('2026-09-04 08:00:00 UTC'));
    }

    public function test_who_is_never_a_candidate(): void
    {
        $this->registered('2026-09-04 09:30:00 UTC', ['onboarding_completed_at' => '2026-09-04 09:40:00']);

        $deleted = $this->registered('2026-09-04 09:30:00 UTC');
        $deleted->delete();

        $control = $this->registered('2026-09-04 09:30:00 UTC');

        $due = UnfinishedAccounts::dueAt(self::at(self::SKOPJE_TEN_AM));

        $this->assertCount(1, $due);
        $this->assertTrue($due->first()->user->is($control));
    }

    public function test_a_user_with_a_device_is_read_in_its_timezone_not_the_home_one(): void
    {
        $newYork = $this->registered('2026-09-04 09:30:00 UTC');
        Device::factory()->for($newYork)->create(['timezone' => 'America/New_York']);
        $noDevice = $this->registered('2026-09-04 09:30:00 UTC');

        $atSkopjeTen = UnfinishedAccounts::dueAt(self::at('2026-09-05 08:00:00 UTC'));
        $this->assertCount(1, $atSkopjeTen);
        $this->assertTrue($atSkopjeTen->first()->user->is($noDevice));

        $atNewYorkTen = UnfinishedAccounts::dueAt(self::at('2026-09-05 14:00:00 UTC'));
        $this->assertCount(1, $atNewYorkTen);
        $this->assertTrue($atNewYorkTen->first()->user->is($newYork));
    }

    public function test_the_push_switch_being_off_does_not_exclude(): void
    {
        $this->registered('2026-09-04 09:30:00 UTC', ['push_enabled' => false]);

        $this->assertSame([[1, 'not_onboarded']], $this->dueAt(self::SKOPJE_TEN_AM));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_users(): void
    {
        $count = function (int $users): int {
            foreach (range(1, $users) as $i) {
                $this->registered('2026-09-04 09:30:00 UTC');
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $due = UnfinishedAccounts::dueAt(self::at(self::SKOPJE_TEN_AM));
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertCount(User::count(), $due);

            return $queries;
        };

        $withTen = $count(10);
        $withThirtyMore = $count(30);

        $this->assertSame($withTen, $withThirtyMore);
        $this->assertLessThanOrEqual(4, $withTen);
    }
}
