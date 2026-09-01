<?php

namespace Tests\Unit\FitnessMetrics;

use App\Enums\Gender;
use App\Services\FitnessMetrics\StrengthScore;
use PHPUnit\Framework\TestCase;

/**
 * The strength arithmetic, exercised with arrays.
 *
 * The set rows these take are the shape CompletedSessions::setLogs() returns, so
 * they can be written by hand instead of seeded through four tables and an HTTP
 * round-trip.
 */
class StrengthScoreTest extends TestCase
{
    public function test_one_rep_max_follows_epley(): void
    {
        // A single is its own one-rep max.
        $this->assertSame(100.0, StrengthScore::oneRepMax(100, 0));
        // 100 × (1 + 10/30)
        $this->assertSame(133.33, round(StrengthScore::oneRepMax(100, 10), 2));
    }

    public function test_relative_strength_is_the_best_set_per_exercise_over_body_weight(): void
    {
        $sets = [
            self::set(exercise: 'Squat', weight: 100, reps: 5),
            self::set(exercise: 'Squat', weight: 120, reps: 5),  // 140, the best squat
            self::set(exercise: 'Bench Press', weight: 60, reps: 10), // 80
        ];

        // (140 + 80) / 80 × 100
        $this->assertSame(275.0, StrengthScore::relativeStrength($sets, 80));
    }

    public function test_extra_sets_of_the_same_lift_do_not_raise_the_score(): void
    {
        $one = [self::set(exercise: 'Squat', weight: 100, reps: 5)];
        $five = array_fill(0, 5, self::set(exercise: 'Squat', weight: 100, reps: 5));

        $this->assertSame(
            StrengthScore::relativeStrength($one, 80),
            StrengthScore::relativeStrength($five, 80),
            'The score measures strength, not volume.'
        );
    }

    public function test_no_body_weight_means_no_score(): void
    {
        $sets = [self::set(exercise: 'Squat', weight: 100, reps: 5)];

        $this->assertSame(0.0, StrengthScore::relativeStrength($sets, 0));
        $this->assertSame([], StrengthScore::muscleGroupScores($sets, 0));
    }

    public function test_levels_sit_at_their_thresholds(): void
    {
        $this->assertSame('BEGINNER', StrengthScore::level(199));
        $this->assertSame('INTERMEDIATE', StrengthScore::level(200));
        $this->assertSame('INTERMEDIATE', StrengthScore::level(399));
        $this->assertSame('ADVANCED', StrengthScore::level(400));
    }

    public function test_thresholds_scale_down_for_women(): void
    {
        $this->assertSame('INTERMEDIATE', StrengthScore::level(160, Gender::Female));
        $this->assertSame('BEGINNER', StrengthScore::level(160, Gender::Male));
        $this->assertSame('ADVANCED', StrengthScore::level(320, Gender::Female));
    }

    public function test_thresholds_scale_down_past_fifty_and_stop_at_thirty_percent(): void
    {
        $this->assertSame(200.0, StrengthScore::thresholds(age: 50)['intermediate']);
        $this->assertSame(180.0, StrengthScore::thresholds(age: 60)['intermediate']);
        // The 0.7 floor: 80 would otherwise scale to 0.7, and 90 below it.
        $this->assertSame(
            StrengthScore::thresholds(age: 80)['intermediate'],
            StrengthScore::thresholds(age: 200)['intermediate'],
        );
        $this->assertSame(140.0, StrengthScore::thresholds(age: 200)['intermediate']);
    }

    public function test_gender_and_age_adjustments_compound(): void
    {
        // 200 × 0.8 × 0.9
        $this->assertEqualsWithDelta(144.0, StrengthScore::thresholds(Gender::Female, 60)['intermediate'], 0.0001);
    }

    public function test_a_muscle_group_needs_three_sets_before_it_scores(): void
    {
        $twoSets = [
            self::set(muscleGroup: 'Chest', weight: 100, reps: 10),
            self::set(muscleGroup: 'Chest', weight: 100, reps: 10),
        ];

        $this->assertSame([], StrengthScore::muscleGroupScores($twoSets, 80));

        $threeSets = [...$twoSets, self::set(muscleGroup: 'Chest', weight: 100, reps: 10)];

        // 133.33 / 80 × 100
        $this->assertSame(['chest' => 167], StrengthScore::muscleGroupScores($threeSets, 80));
    }

    public function test_display_groups_average_whichever_parts_were_trained(): void
    {
        $this->assertSame(
            ['back' => 150],
            StrengthScore::asDisplayGroups(['lats' => 100, 'upper back' => 200]),
            'Two of the three back groups: the mean of those two, not of three.'
        );
    }

    public function test_display_groups_omit_anything_untrained(): void
    {
        $this->assertSame(
            ['chest' => 100, 'legs' => 200],
            StrengthScore::asDisplayGroups(['chest' => 100, 'quadriceps' => 200]),
        );
    }

    /**
     * One row of CompletedSessions::setLogs().
     */
    private static function set(
        string $exercise = 'Bench Press',
        string $muscleGroup = 'Chest',
        float $weight = 100,
        int $reps = 10,
    ): object {
        return (object) [
            'exercise_name' => $exercise,
            'muscle_group_name' => $muscleGroup,
            'weight' => $weight,
            'reps' => $reps,
        ];
    }
}
