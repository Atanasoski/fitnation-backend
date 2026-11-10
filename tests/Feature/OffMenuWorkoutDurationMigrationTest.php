<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The migration that moves stored session lengths off the menu
 * (30/45/60/90/120) onto it: each goes to the nearest menu value of the same
 * Tier, ties to the shorter option. On-menu and empty values stay, and a
 * member it moved can save profile changes again.
 */
class OffMenuWorkoutDurationMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_10_160000_move_off_menu_workout_durations_onto_the_menu.php';

    private function memberStoring(?int $minutes): User
    {
        $member = User::factory()->create();
        $member->profile()->update(['workout_duration_minutes' => $minutes]);

        return $member;
    }

    private function storedDuration(User $member): ?int
    {
        return $member->profile()->first()->workout_duration_minutes;
    }

    public function test_a_stored_75_moves_to_90(): void
    {
        $member = $this->memberStoring(75);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame(90, $this->storedDuration($member));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function offMenuDurations(): array
    {
        return [
            'Express: 15 moves up to 30' => [15, 30],
            'Express: 39 moves down to 30' => [39, 30],
            'Standard: 40 moves to 45' => [40, 45],
            'Standard: 52 is nearer 45' => [52, 45],
            'Standard: 53 is nearer 60' => [53, 60],
            'Extended: 61 stays Extended at 90, not 60' => [61, 90],
            'Extended: 105 is a tie and goes to the shorter 90' => [105, 90],
            'Extended: 106 is nearer 120' => [106, 120],
            'Extended: 180 moves down to 120' => [180, 120],
        ];
    }

    #[DataProvider('offMenuDurations')]
    public function test_an_off_menu_duration_moves_to_the_nearest_menu_value_of_its_tier(int $stored, int $expected): void
    {
        $member = $this->memberStoring($stored);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame($expected, $this->storedDuration($member));
    }

    public function test_on_menu_and_empty_durations_are_untouched(): void
    {
        $onMenu = [];
        foreach ([30, 45, 60, 90, 120] as $minutes) {
            $onMenu[$minutes] = $this->memberStoring($minutes);
        }
        $empty = $this->memberStoring(null);
        $this->memberStoring(75);

        (require database_path(self::MIGRATION))->up();

        foreach ($onMenu as $minutes => $member) {
            $this->assertSame($minutes, $this->storedDuration($member));
        }
        $this->assertNull($this->storedDuration($empty));
    }

    /**
     * The bug the migration fixes: the app reads the profile, the member
     * changes one field, and the app saves the whole profile back, stored
     * duration included. With 75 stored, that save was rejected.
     */
    public function test_a_migrated_member_can_change_their_goal_with_the_app_echoing_their_duration(): void
    {
        $member = $this->memberStoring(75);
        $member->profile()->update(['fitness_goal' => FitnessGoal::MuscleGain]);

        (require database_path(self::MIGRATION))->up();

        $profile = $this->actingAs($member, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->json('user.profile');

        $this->assertSame(90, $profile['workout_duration_minutes']);

        $this->actingAs($member, 'sanctum')
            ->putJson('/api/profile', [...$profile, 'fitness_goal' => FitnessGoal::Strength->value])
            ->assertOk()
            ->assertJsonPath('user.profile.fitness_goal', FitnessGoal::Strength->value)
            ->assertJsonPath('user.profile.workout_duration_minutes', 90);
    }

    public function test_a_migrated_member_can_change_their_training_days_with_the_app_echoing_their_duration(): void
    {
        $member = $this->memberStoring(75);
        $member->profile()->update(['training_days_per_week' => 3]);

        (require database_path(self::MIGRATION))->up();

        $profile = $this->actingAs($member, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->json('user.profile');

        $this->actingAs($member, 'sanctum')
            ->patchJson('/api/profile', [...$profile, 'training_days_per_week' => 4])
            ->assertOk()
            ->assertJsonPath('user.profile.training_days_per_week', 4)
            ->assertJsonPath('user.profile.workout_duration_minutes', 90);
    }

    public function test_it_can_run_twice_and_rolling_back_keeps_the_menu_value(): void
    {
        $member = $this->memberStoring(75);

        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $migration->up();

        $this->assertSame(90, $this->storedDuration($member));

        // The original 75 is not recorded anywhere, so down() cannot restore it.
        $migration->down();

        $this->assertSame(90, $this->storedDuration($member));
    }
}
