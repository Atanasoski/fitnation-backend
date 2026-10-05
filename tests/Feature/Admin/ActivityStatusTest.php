<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ActivityStatus;
use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Admin\ActivityStatuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Activity Status agreement test: for every fixture, the per-user answer is
 * the expected label, and the query constraint for each label returns exactly
 * the users carrying it. Fixtures sit on each side of every day boundary.
 */
class ActivityStatusTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    /**
     * fixture name => [expected status, user attributes, completed sessions (days ago), other sessions (days ago)]
     *
     * @return array<string, array{ActivityStatus, array<string, mixed>, list<float>, list<float>}>
     */
    private function fixtures(): array
    {
        $onboarded = fn (float $daysAgo) => ['onboarding_completed_at' => $this->daysAgo($daysAgo)];

        return [
            'deleted, trained yesterday' => [ActivityStatus::Deleted, [...$onboarded(30), 'deleted_at' => $this->daysAgo(1)], [1], []],
            'deleted, never onboarded' => [ActivityStatus::Deleted, ['onboarding_completed_at' => null, 'deleted_at' => $this->daysAgo(1)], [], []],
            'unverified, not onboarded' => [ActivityStatus::Unfinished, ['email_verified_at' => null, 'onboarding_completed_at' => null], [], []],
            'unverified, onboarded and training' => [ActivityStatus::Unfinished, [...$onboarded(30), 'email_verified_at' => null], [1], []],
            'verified, not onboarded' => [ActivityStatus::Unfinished, ['onboarding_completed_at' => null], [], []],
            'trained 6 days ago' => [ActivityStatus::Active, $onboarded(60), [6], []],
            'trained a minute short of 7 days ago' => [ActivityStatus::Active, $onboarded(60), [7 - 1 / 1440], []],
            'trained 7 days ago' => [ActivityStatus::Slipping, $onboarded(60), [7], []],
            'trained 8 days ago' => [ActivityStatus::Slipping, $onboarded(60), [8], []],
            'trained 13 days ago' => [ActivityStatus::Slipping, $onboarded(60), [13], []],
            'trained 14 days ago' => [ActivityStatus::Slipping, $onboarded(60), [14], []],
            'trained 15 days ago' => [ActivityStatus::Inactive, $onboarded(60), [15], []],
            'trained 40 and 2 days ago' => [ActivityStatus::Active, $onboarded(60), [40, 2], []],
            'onboarded 13 days ago, never trained' => [ActivityStatus::New, $onboarded(13), [], []],
            'onboarded 14 days ago, never trained' => [ActivityStatus::New, $onboarded(14), [], []],
            'onboarded 15 days ago, never trained' => [ActivityStatus::Inactive, $onboarded(15), [], []],
            'onboarded 20 days ago, only an unfinished session yesterday' => [ActivityStatus::Inactive, $onboarded(20), [], [1]],
            'onboarded 2 days ago, only an unfinished session yesterday' => [ActivityStatus::New, $onboarded(2), [], [1]],
        ];
    }

    public function test_the_status_for_a_user_and_the_query_constraint_agree_on_every_fixture(): void
    {
        $this->travelTo(self::NOW);

        $expected = [];
        foreach ($this->fixtures() as $name => [$status, $attributes, $completed, $other]) {
            $user = $this->fixture($attributes, $completed, $other);
            $expected[$name] = [$user->id, $status];
        }

        foreach ($expected as $name => [$id, $status]) {
            $user = User::withTrashed()->findOrFail($id);
            $this->assertSame($status, ActivityStatuses::for($user), "per-user status for: {$name}");
        }

        $all = User::withTrashed()->get();
        $batch = ActivityStatuses::forUsers($all);
        foreach ($expected as $name => [$id, $status]) {
            $this->assertSame($status, $batch[$id], "batch status for: {$name}");
        }

        // Session factories create template owners too; look only at the fixtures.
        $ids = collect($expected)->pluck(0)->all();

        foreach (ActivityStatus::cases() as $status) {
            $want = collect($expected)->filter(fn ($row) => $row[1] === $status)->map(fn ($row) => $row[0])->sort()->values()->all();
            $got = ActivityStatuses::constrain(User::query()->whereKey($ids), $status)->pluck('id')->sort()->values()->all();

            $this->assertSame($want, $got, "constraint for {$status->value}");
        }
    }

    public function test_the_status_moves_with_the_clock(): void
    {
        $this->travelTo(self::NOW);
        $user = $this->fixture(['onboarding_completed_at' => $this->daysAgo(60)], [6], []);

        $this->assertSame(ActivityStatus::Active, ActivityStatuses::for($user));

        $this->travel(1)->days();

        $this->assertSame(ActivityStatus::Slipping, ActivityStatuses::for($user));
        $this->assertSame([$user->id], ActivityStatuses::constrain(User::query()->whereKey($user->id), ActivityStatus::Slipping)->pluck('id')->all());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<float>  $completedDaysAgo
     * @param  list<float>  $otherDaysAgo
     */
    private function fixture(array $attributes, array $completedDaysAgo, array $otherDaysAgo): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()->subDays(30), ...$attributes]);

        foreach ($completedDaysAgo as $days) {
            WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'status' => WorkoutSessionStatus::Completed,
                'performed_at' => $this->daysAgo($days),
                'completed_at' => $this->daysAgo($days),
            ]);
        }

        foreach ($otherDaysAgo as $days) {
            WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'status' => WorkoutSessionStatus::Active,
                'performed_at' => $this->daysAgo($days),
                'completed_at' => $this->daysAgo($days),
            ]);
        }

        return $user;
    }

    private function daysAgo(float $days): \Illuminate\Support\Carbon
    {
        return now()->subMinutes((int) round($days * 1440));
    }
}
