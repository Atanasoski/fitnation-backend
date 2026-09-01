<?php

namespace Tests\Unit\FitnessMetrics;

use App\Services\FitnessMetrics\StrengthBalance;
use PHPUnit\Framework\TestCase;

/**
 * The balance arithmetic, exercised with arrays.
 *
 * All of this used to be private behind getMetrics(), so pinning any of it meant
 * building muscle groups, exercises, sessions and set logs and calling the
 * endpoint. It is a function of a list of numbers and is now tested as one.
 */
class StrengthBalanceTest extends TestCase
{
    public function test_no_training_is_not_balance(): void
    {
        $this->assertSame(0.0, StrengthBalance::percentage([]));
        $this->assertSame(0.0, StrengthBalance::percentage(['chest' => 0, 'lats' => 0]));
    }

    public function test_one_muscle_group_scores_zero_however_much_volume_it_takes(): void
    {
        // Coverage without evenness. A single group has no distribution to be
        // even about, and calling that perfectly balanced would make the
        // narrowest possible training the best possible score.
        $this->assertSame(0.0, StrengthBalance::percentage(['chest' => 100]));
    }

    public function test_training_every_group_evenly_scores_one_hundred(): void
    {
        $even = array_fill_keys(StrengthBalance::MUSCLE_GROUPS, 100 / 17);

        $this->assertSame(100.0, round(StrengthBalance::percentage($even), 6));
    }

    public function test_concentrating_volume_scores_below_spreading_it(): void
    {
        $groups = array_slice(StrengthBalance::MUSCLE_GROUPS, 0, 4);

        $even = array_fill_keys($groups, 25);
        $skewed = array_combine($groups, [91, 3, 3, 3]);

        $this->assertGreaterThan(
            StrengthBalance::percentage($skewed),
            StrengthBalance::percentage($even),
        );
    }

    public function test_covering_more_groups_scores_above_covering_fewer(): void
    {
        $four = array_fill_keys(array_slice(StrengthBalance::MUSCLE_GROUPS, 0, 4), 25);
        $twelve = array_fill_keys(array_slice(StrengthBalance::MUSCLE_GROUPS, 0, 12), 100 / 12);

        $this->assertGreaterThan(
            StrengthBalance::percentage($four),
            StrengthBalance::percentage($twelve),
        );
    }

    /**
     * The thresholds the levels are calibrated against, at their boundaries.
     */
    public function test_levels_sit_at_their_documented_thresholds(): void
    {
        $this->assertSame('NEEDS_IMPROVEMENT', StrengthBalance::level(0));
        $this->assertSame('NEEDS_IMPROVEMENT', StrengthBalance::level(39.9));
        $this->assertSame('FAIR', StrengthBalance::level(40));
        $this->assertSame('FAIR', StrengthBalance::level(59.9));
        $this->assertSame('GOOD', StrengthBalance::level(60));
        $this->assertSame('GOOD', StrengthBalance::level(79.9));
        $this->assertSame('EXCELLENT', StrengthBalance::level(80));
        $this->assertSame('EXCELLENT', StrengthBalance::level(100));
    }

    public function test_shares_are_volume_as_a_percentage_of_the_total(): void
    {
        $this->assertSame(
            ['chest' => 50.0, 'lats' => 30.0, 'biceps' => 20.0],
            StrengthBalance::shares(['Chest' => 500, 'Lats' => 300, 'Biceps' => 200]),
        );
    }

    public function test_shares_of_nothing_is_an_empty_list_not_a_row_of_zeros(): void
    {
        $this->assertSame([], StrengthBalance::shares([]));
        $this->assertSame([], StrengthBalance::shares(['Chest' => 0]));
    }

    public function test_reported_shares_name_every_tracked_group_in_tracked_order(): void
    {
        $reported = StrengthBalance::sharesWithUntrainedGroups(['Chest' => 750, 'Quadriceps' => 250]);

        $this->assertSame(StrengthBalance::MUSCLE_GROUPS, array_keys($reported));
        $this->assertSame(75, $reported['chest']);
        $this->assertSame(25, $reported['quadriceps']);
        $this->assertSame(0, $reported['calves']);
    }

    public function test_volume_against_an_untracked_group_is_still_reported(): void
    {
        $reported = StrengthBalance::sharesWithUntrainedGroups(['Chest' => 500, 'Neck' => 500]);

        $this->assertSame(50, $reported['chest']);
        $this->assertSame(50, $reported['neck']);
    }
}
