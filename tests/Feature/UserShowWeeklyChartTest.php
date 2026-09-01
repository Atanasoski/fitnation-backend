<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Partner;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workout-frequency chart on the trainer's view of a user.
 *
 * UserController used to re-derive this itself, line for line, from a copy of
 * the same week-bucketing the metrics service already had — while holding a
 * FitnessMetricsService three lines earlier. It now asks WeeklyProgress. This
 * pins the shape the chart JS reads (`label` and `count`, per
 * resources/js/components/chart/user-progress.js) so the two cannot part ways.
 */
class UserShowWeeklyChartTest extends TestCase
{
    use RefreshDatabase;

    /** Wednesday, so the current week is partial and the labels are fixed. */
    private const NOW = '2026-03-11 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_chart_gets_twelve_weeks_oldest_first_with_zeros_for_untrained_weeks(): void
    {
        $partner = Partner::factory()->create();
        $trainer = User::factory()->create(['partner_id' => $partner->id]);
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->completedSessionsOn($user, '2026-03-02 10:00', 2);
        $this->completedSessionsOn($user, '2026-02-23 10:00', 1);
        // The week in progress is the twelfth and last entry, not excluded.
        $this->completedSessionsOn($user, '2026-03-09 10:00', 3);

        $response = $this->actingAs($trainer)->get(route('users.show', $user));

        $response->assertOk();

        $this->assertSame([
            ['week' => '2025-12-22', 'label' => 'Dec 22', 'count' => 0],
            ['week' => '2025-12-29', 'label' => 'Dec 29', 'count' => 0],
            ['week' => '2026-01-05', 'label' => 'Jan 05', 'count' => 0],
            ['week' => '2026-01-12', 'label' => 'Jan 12', 'count' => 0],
            ['week' => '2026-01-19', 'label' => 'Jan 19', 'count' => 0],
            ['week' => '2026-01-26', 'label' => 'Jan 26', 'count' => 0],
            ['week' => '2026-02-02', 'label' => 'Feb 02', 'count' => 0],
            ['week' => '2026-02-09', 'label' => 'Feb 09', 'count' => 0],
            ['week' => '2026-02-16', 'label' => 'Feb 16', 'count' => 0],
            ['week' => '2026-02-23', 'label' => 'Feb 23', 'count' => 1],
            ['week' => '2026-03-02', 'label' => 'Mar 02', 'count' => 2],
            ['week' => '2026-03-09', 'label' => 'Mar 09', 'count' => 3],
        ], $response->viewData('weeklyWorkoutData'));
    }

    public function test_the_chart_counts_completed_sessions_only(): void
    {
        $partner = Partner::factory()->create();
        $trainer = User::factory()->create(['partner_id' => $partner->id]);
        $user = User::factory()->create(['partner_id' => $partner->id]);

        $this->completedSessionsOn($user, '2026-03-02 10:00', 1);

        // A session abandoned part-way, timestamp and all, is not a workout.
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
            'performed_at' => Carbon::parse('2026-03-02 15:00'),
            'completed_at' => Carbon::parse('2026-03-02 16:00'),
        ]);

        $weeks = collect($this->actingAs($trainer)->get(route('users.show', $user))->viewData('weeklyWorkoutData'));

        $this->assertSame(1, $weeks->firstWhere('week', '2026-03-02')['count']);
    }

    private function completedSessionsOn(User $user, string $performedAt, int $count): void
    {
        WorkoutSession::factory()->count($count)->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => Carbon::parse($performedAt),
            'completed_at' => Carbon::parse($performedAt)->addHour(),
        ]);
    }
}
