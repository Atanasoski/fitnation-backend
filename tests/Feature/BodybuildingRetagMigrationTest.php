<?php

namespace Tests\Feature;

use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\TrainingStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/** The ADR-0014 data migration: tags added, Goblet Squat moved, missing names tolerated. */
class BodybuildingRetagMigrationTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_11_20_100000_retag_catalogue_for_bodybuilding_generation.php');
    }

    private function functionalOnly(string $name, string $equipment): Exercise
    {
        $exercise = $this->catalogExercise('ROW', 'UPPER_PULL', $equipment, null, ['name' => $name]);
        $exercise->trainingStyles()->sync([
            TrainingStyle::firstOrCreate(['code' => 'FUNCTIONAL'], ['name' => 'Functional', 'display_order' => 20])->id,
        ]);

        return $exercise;
    }

    private function styles(Exercise $exercise): array
    {
        return $exercise->fresh()->trainingStyles->pluck('code')->sort()->values()->all();
    }

    public function test_it_adds_bodybuilding_keeping_functional_and_touches_nothing_else(): void
    {
        $trx = $this->functionalOnly('TRX Row', 'TRX');
        $monsterWalk = $this->functionalOnly('Band Lateral Monster Walk', 'BAND');

        $this->migration()->up();

        $this->assertSame(['BODYBUILDING', 'FUNCTIONAL'], $this->styles($trx));
        $this->assertSame(['FUNCTIONAL'], $this->styles($monsterWalk));
    }

    public function test_it_moves_goblet_squat_to_dumbbell_and_tolerates_missing_names(): void
    {
        $goblet = $this->functionalOnly('Goblet Squat', 'KETTLEBELL');
        EquipmentType::firstOrCreate(['code' => 'DUMBBELL'], ['name' => 'Dumbbell', 'display_order' => 0]);

        $this->migration()->up();

        $this->assertSame('DUMBBELL', $goblet->fresh()->equipmentType->code);
    }

    public function test_it_is_idempotent_and_reversible(): void
    {
        $trx = $this->functionalOnly('TRX Row', 'TRX');
        $goblet = $this->functionalOnly('Goblet Squat', 'KETTLEBELL');
        EquipmentType::firstOrCreate(['code' => 'DUMBBELL'], ['name' => 'Dumbbell', 'display_order' => 0]);

        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame(['BODYBUILDING', 'FUNCTIONAL'], $this->styles($trx));

        $this->migration()->down();

        $this->assertSame(['FUNCTIONAL'], $this->styles($trx));
        $this->assertSame('KETTLEBELL', $goblet->fresh()->equipmentType->code);
    }
}
