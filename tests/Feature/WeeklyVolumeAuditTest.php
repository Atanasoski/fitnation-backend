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
        'chest' => ['main_press', 'incline_press', 'chest_iso'],
        'quads' => ['quad_compound', 'quad_iso'],
        'back' => ['vertical_pull', 'heavy_row', 'second_row', 'lats_iso'],
        'hamstrings' => ['hinge', 'ham_iso'],
        'biceps' => ['biceps'],
    ];

    public function test_a_four_day_balanced_split_delivers_the_declared_weekly_direct_sets(): void
    {
        $this->seed(WorkoutSplitSeeder::class);
        $this->seedFullGymCatalog();

        $user = User::factory()->create();
        $user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $split = WorkoutSplit::getSplit(4, SplitFocus::Balanced);
        $this->assertCount(4, $split);

        $weekRows = collect(app(BlueprintWorkoutGenerator::class)->generatePlan(
            $user,
            ['duration_minutes' => 60], // Standard tier
            array_values($split),
        ))->flatMap(fn ($day) => $day->rows);

        $setsBySlotKey = $weekRows
            ->groupBy('slot_key')
            ->map(fn ($rows) => $rows->sum('target_sets'));

        $directSetsFor = fn (string $muscle) => collect(self::DIRECT_SLOTS_BY_MUSCLE[$muscle])
            ->sum(fn (string $slotKey) => $setsBySlotKey[$slotKey] ?? 0);

        // On paper: PUSH standard carries main_press (3) + incline_press (3);
        // each LOWER day carries quad_compound (3) + quad_iso (3), twice a
        // week; PULL standard carries three back slots (9) and biceps (3);
        // hinge (3) + ham_iso (3) land twice a week. chest_iso and lats_iso
        // are tier-3 and absent at Standard.
        $this->assertSame(6, $directSetsFor('chest'), 'chest direct sets per week');
        $this->assertSame(12, $directSetsFor('quads'), 'quads direct sets per week');
        $this->assertSame(9, $directSetsFor('back'), 'back direct sets per week');
        $this->assertSame(12, $directSetsFor('hamstrings'), 'hamstrings direct sets per week');
        $this->assertSame(3, $directSetsFor('biceps'), 'biceps direct sets per week');

        // And the Standard tier's quality-volume target holds day by day:
        // four days × five slots × three sets.
        $this->assertSame(60, $weekRows->sum('target_sets'), 'total weekly sets');
    }

    /**
     * One level-0 candidate per role across the four day types this split
     * resolves to, so every Standard slot fills without relaxation.
     */
    private function seedFullGymCatalog(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('SHOULDER_RAISE', 'UPPER_PUSH', 'DUMBBELL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');
        $this->catalogExercise('PULL_VERTICAL', 'UPPER_PULL', 'CABLE');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'CABLE', 'HORIZONTAL');
        $this->catalogExercise('REAR_DELT_FLY', 'UPPER_PULL', 'DUMBBELL');
        $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'DUMBBELL');
        $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL');
        $this->catalogExercise('HINGE', 'LOWER', 'BARBELL');
        $this->catalogExercise('KNEE_EXTENSION', 'LOWER', 'MACHINE');
        $this->catalogExercise('KNEE_FLEXION', 'LOWER', 'MACHINE');
        $this->catalogExercise('CALF_RAISE', 'LOWER', 'MACHINE');
    }
}
