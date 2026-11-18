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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Every Split, at every training frequency, audited by target region
     * rather than by muscle (#146).
     *
     * Muscle-level auditing cannot honestly reach these frequencies. The
     * 1- to 3-day Splits are built from Full Body days, whose
     * `lower_compound` Slot accepts a squat or a hinge, whose `lower_iso`
     * accepts knee flexion or extension, and whose `delts_arms` accepts a
     * raise, a curl or an extension — so the muscle a Slot trains is not
     * knowable from the Blueprint, only from whichever Exercise the pool
     * happened to hand it. Calling those sets "quads" would be the same
     * false precision that keeps `press_2` out of the chest list above.
     *
     * A Slot's target regions, by contrast, are declared, so these numbers
     * are exact. The counts below are committed literals read off the
     * Blueprint tables, not computed at run time: that is the whole point
     * — a Split or a Blueprint that changes volume has to change a number
     * here, in front of a reviewer. Note that a Slot serving two regions
     * counts toward both (only FULL_BODY's `delts_arms` does, for
     * UPPER_PUSH and ARMS), so the regions sum to more than the total.
     *
     * Zeroes are the findings worth keeping an eye on, and every one of
     * them is a consequence of a tier-3 Slot being absent at Standard:
     *
     * - upper focus at 1 and 2 days trains no LOWER at all (ADR-0013
     *   accepts this deliberately), and at 1 day no ARMS either
     * - balanced at 3 days, the most common frequency, delivers no direct
     *   ARMS work: it is Upper / Legs / Upper, and UPPER's arms Slot is
     *   tier 3
     * - lower focus at 3 and 4 days delivers no direct ARMS work either,
     *   for the same reason
     *
     * @return array<string, array{0: SplitFocus, 1: int, 2: array<string, int>, 3: int}>
     */
    public static function everySplit(): array
    {
        $table = [
            'balanced' => [
                1 => [['ARMS' => 3, 'LOWER' => 6, 'UPPER_PULL' => 3, 'UPPER_PUSH' => 6], 15],
                2 => [['LOWER' => 18, 'UPPER_PULL' => 6, 'UPPER_PUSH' => 6], 30],
                3 => [['LOWER' => 15, 'UPPER_PULL' => 12, 'UPPER_PUSH' => 18], 45],
                4 => [['ARMS' => 6, 'LOWER' => 30, 'UPPER_PULL' => 12, 'UPPER_PUSH' => 12], 60],
                5 => [['ARMS' => 6, 'LOWER' => 30, 'UPPER_PULL' => 18, 'UPPER_PUSH' => 21], 75],
                6 => [['ARMS' => 12, 'LOWER' => 30, 'UPPER_PULL' => 24, 'UPPER_PUSH' => 24], 90],
                7 => [['ARMS' => 15, 'LOWER' => 36, 'UPPER_PULL' => 27, 'UPPER_PUSH' => 30], 105],
            ],
            'upper_focus' => [
                1 => [['UPPER_PULL' => 6, 'UPPER_PUSH' => 9], 15],
                2 => [['ARMS' => 6, 'UPPER_PULL' => 12, 'UPPER_PUSH' => 12], 30],
                3 => [['ARMS' => 6, 'LOWER' => 15, 'UPPER_PULL' => 12, 'UPPER_PUSH' => 12], 45],
                4 => [['ARMS' => 6, 'LOWER' => 15, 'UPPER_PULL' => 18, 'UPPER_PUSH' => 21], 60],
                5 => [['ARMS' => 9, 'LOWER' => 15, 'UPPER_PULL' => 18, 'UPPER_PUSH' => 33], 75],
                6 => [['ARMS' => 12, 'LOWER' => 15, 'UPPER_PULL' => 30, 'UPPER_PUSH' => 33], 90],
                7 => [['ARMS' => 12, 'LOWER' => 30, 'UPPER_PULL' => 30, 'UPPER_PUSH' => 33], 105],
            ],
            'lower_focus' => [
                1 => [['ARMS' => 3, 'LOWER' => 6, 'UPPER_PULL' => 3, 'UPPER_PUSH' => 6], 15],
                2 => [['ARMS' => 3, 'LOWER' => 21, 'UPPER_PULL' => 3, 'UPPER_PUSH' => 6], 30],
                3 => [['LOWER' => 30, 'UPPER_PULL' => 6, 'UPPER_PUSH' => 9], 45],
                4 => [['LOWER' => 45, 'UPPER_PULL' => 6, 'UPPER_PUSH' => 9], 60],
                5 => [['ARMS' => 6, 'LOWER' => 45, 'UPPER_PULL' => 12, 'UPPER_PUSH' => 12], 75],
                6 => [['ARMS' => 6, 'LOWER' => 45, 'UPPER_PULL' => 18, 'UPPER_PUSH' => 21], 90],
                7 => [['ARMS' => 6, 'LOWER' => 60, 'UPPER_PULL' => 18, 'UPPER_PUSH' => 21], 105],
            ],
        ];

        $cases = [];
        foreach ($table as $focus => $byDays) {
            foreach ($byDays as $days => [$regionSets, $total]) {
                $cases["{$focus}, {$days} day".($days > 1 ? 's' : '')] = [
                    SplitFocus::from($focus),
                    $days,
                    $regionSets,
                    $total,
                ];
            }
        }

        return $cases;
    }

    /**
     * @param  array<string, int>  $expectedRegionSets
     */
    #[DataProvider('everySplit')]
    public function test_a_split_delivers_the_declared_weekly_region_sets(
        SplitFocus $focus,
        int $days,
        array $expectedRegionSets,
        int $expectedTotal,
    ): void {
        $this->seed(WorkoutSplitSeeder::class);
        $this->seedFullGymCatalog();

        $user = User::factory()->create();
        $user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $split = WorkoutSplit::getSplit($days, $focus);
        $this->assertCount($days, $split, "the catalog holds {$days} days for {$focus->value}");

        $weekRows = collect(app(BlueprintWorkoutGenerator::class)->generatePlan(
            $user,
            ['duration_minutes' => 60], // Standard tier
            array_values($split),
        ))->flatMap(fn ($day) => $day->rows);

        $this->assertSame(
            $expectedRegionSets,
            $this->setsByRegion($weekRows),
            "weekly sets per target region ({$focus->value}, {$days}d)",
        );

        $this->assertSame(
            $expectedTotal,
            $weekRows->sum('target_sets'),
            "total weekly sets ({$focus->value}, {$days}d)",
        );
    }

    /**
     * Weekly sets per target region, read off each row's Slot. A Slot
     * serving two regions counts toward both.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $weekRows
     * @return array<string, int>
     */
    private function setsByRegion(\Illuminate\Support\Collection $weekRows): array
    {
        $byRegion = [];

        foreach ($weekRows as $row) {
            foreach ($this->slotRegions($row['slot_key']) as $region) {
                $byRegion[$region] = ($byRegion[$region] ?? 0) + $row['target_sets'];
            }
        }

        ksort($byRegion);

        return $byRegion;
    }

    /**
     * @return list<string>
     */
    private function slotRegions(string $slotKey): array
    {
        static $regionsBySlot = null;

        if ($regionsBySlot === null) {
            $config = require base_path('config/workout_blueprints.php');
            $regionsBySlot = [];

            foreach (array_merge(array_values($config['blueprints']), array_values($config['fragments'])) as $group) {
                foreach ($group['slots'] as $slot) {
                    $regionsBySlot[$slot['key']] = $slot['regions'];
                }
            }
        }

        return $regionsBySlot[$slotKey] ?? [];
    }
}
