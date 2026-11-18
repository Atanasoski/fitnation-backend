<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\SplitFocus;
use App\Enums\TrainingExperience;
use App\Models\User;
use App\Models\WorkoutSplit;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * The weekly volume audit (#107, story 26): with Blueprints, per-muscle
 * weekly volume is computable from blueprint × split on paper — no need to
 * run the selection code to know what a week delivers. One seeded split
 * (4-day balanced: Push / Lower / Pull / Lower) at the Standard tier must
 * deliver exactly the direct set counts the blueprints declare.
 */
class WeeklyVolumeAuditTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    /**
     * Which slots train each audited muscle directly, read off the signed-off
     * slot tables in ADR-0010 / #107. chest_iso and abs are tier-3 and absent
     * at Standard.
     */
    private const DIRECT_SLOTS_BY_MUSCLE = [
        // UPPER's press and pull slots carry their own keys, so a split
        // containing an Upper Body day (every upper-focus split) is audited
        // through them as well (#143). press_2 is left out for the same
        // reason overhead_press is: its role admits a VERTICAL press, so a
        // delt movement can fill it and its sets are not reliably chest.
        'chest' => ['main_press', 'incline_press', 'chest_iso', 'chest_press'],
        'quads' => ['quad_compound', 'quad_iso'],
        'back' => ['vertical_pull', 'heavy_row', 'second_row', 'lats_iso', 'heavy_pull', 'second_pull'],
        'hamstrings' => ['hinge', 'ham_iso'],
        'biceps' => ['biceps'],
    ];

    public function test_a_four_day_balanced_split_delivers_the_declared_weekly_direct_sets(): void
    {
        // On paper: PUSH standard carries main_press (3) + incline_press (3);
        // each LOWER day carries quad_compound (3) + quad_iso (3), twice a
        // week; PULL standard carries three back slots (9) and biceps (3);
        // hinge (3) + ham_iso (3) land twice a week. chest_iso and lats_iso
        // are tier-3 and absent at Standard.
        $this->assertWeeklyDirectSets(SplitFocus::Balanced, [
            'chest' => 6,
            'quads' => 12,
            'back' => 9,
            'hamstrings' => 12,
            'biceps' => 3,
        ]);
    }

    /**
     * Push / Pull / Upper Body / Legs (#143). The Upper Body day adds a
     * second chest and a second back exposure in place of the balanced
     * split's second Legs day, so chest and back rise while quads and
     * hamstrings halve. Its arms slot is tier-3, so biceps stays at PULL's
     * three sets, and its press_2 slot is not counted as chest.
     */
    public function test_a_four_day_upper_focus_split_delivers_the_declared_weekly_direct_sets(): void
    {
        $this->assertWeeklyDirectSets(SplitFocus::UpperFocus, [
            'chest' => 9,
            'quads' => 6,
            'back' => 15,
            'hamstrings' => 6,
            'biceps' => 3,
        ]);
    }

    /**
     * Legs / Upper Body / Legs / Legs+Core (#146): three leg days to one
     * upper, so quads and hamstrings carry six sets on each of three days
     * while the single Upper Body day supplies all the upper-body work.
     *
     * Biceps is zero on purpose, and is the number to look at if this
     * split is ever revisited: an Upper Body day's arms Slot is tier-3
     * (ADR-0010), so at the Standard Tier this member gets no direct arm
     * work at all. The 2:2 shape this replaced carried tier-1 biceps on
     * its Pull day — and was the balanced split reordered, which is why
     * it had to go.
     */
    public function test_a_four_day_lower_focus_split_delivers_the_declared_weekly_direct_sets(): void
    {
        $this->assertWeeklyDirectSets(SplitFocus::LowerFocus, [
            'chest' => 3,
            'quads' => 18,
            'back' => 6,
            'hamstrings' => 18,
            'biceps' => 0,
        ]);
    }

    /**
     * The audit itself: a four-day Split at the Standard Tier delivers
     * exactly the direct sets its Blueprints declare, and the Standard
     * tier's quality-volume target — four days × five slots × three sets.
     *
     * @param  array<string, int>  $expectedDirectSets  muscle => sets per week
     */
    private function assertWeeklyDirectSets(SplitFocus $focus, array $expectedDirectSets): void
    {
        $this->seed(WorkoutSplitSeeder::class);
        $this->seedFullGymCatalog();

        $user = User::factory()->create();
        $user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $split = WorkoutSplit::getSplit(4, $focus);
        $this->assertCount(4, $split);

        $weekRows = collect(app(BlueprintWorkoutGenerator::class)->generatePlan(
            $user,
            ['duration_minutes' => 60], // Standard tier
            array_values($split),
        ))->flatMap(fn ($day) => $day->rows);

        $setsBySlotKey = $weekRows
            ->groupBy('slot_key')
            ->map(fn ($rows) => $rows->sum('target_sets'));

        foreach ($expectedDirectSets as $muscle => $expected) {
            $actual = collect(self::DIRECT_SLOTS_BY_MUSCLE[$muscle])
                ->sum(fn (string $slotKey) => $setsBySlotKey[$slotKey] ?? 0);

            $this->assertSame($expected, $actual, "{$muscle} direct sets per week ({$focus->value})");
        }

        $this->assertSame(60, $weekRows->sum('target_sets'), "total weekly sets ({$focus->value})");
    }
}
