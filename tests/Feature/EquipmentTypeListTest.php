<?php

namespace Tests\Feature;

use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\TrainingStyle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * GET /equipment-types: the full list for the catalogue, or with
 * ?training_style=CODE only the equipment the member's Partner Library has an
 * Exercise of that style for (ADR-0014).
 */
class EquipmentTypeListTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    private Partner $partner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::factory()->sponsor()->create();
        $this->member = User::factory()->create(['partner_id' => $this->partner->id]);
    }

    private function inLibrary(Exercise $exercise): Exercise
    {
        $exercise->partners()->attach($this->partner->id);

        return $exercise;
    }

    private function functionalOnly(Exercise $exercise): Exercise
    {
        $exercise->trainingStyles()->sync([
            TrainingStyle::firstOrCreate(['code' => 'FUNCTIONAL'], ['name' => 'Functional', 'display_order' => 20])->id,
        ]);

        return $exercise;
    }

    /**
     * @return list<string>
     */
    private function codes(string $query = ''): array
    {
        $response = $this->actingAs($this->member, 'sanctum')->getJson('/api/equipment-types'.$query);
        $response->assertOk();

        return collect($response->json('data'))->pluck('code')->all();
    }

    public function test_filtered_list_keeps_equipment_with_a_bodybuilding_exercise_in_the_library(): void
    {
        $this->inLibrary($this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL'));

        $this->assertSame(['DUMBBELL'], $this->codes('?training_style=BODYBUILDING'));
    }

    public function test_filtered_list_excludes_equipment_whose_exercises_are_all_functional(): void
    {
        $this->inLibrary($this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL'));
        $this->inLibrary($this->functionalOnly($this->catalogExercise('SQUAT', 'LOWER', 'KETTLEBELL')));

        $this->assertSame(['DUMBBELL'], $this->codes('?training_style=BODYBUILDING'));
    }

    public function test_filtered_list_excludes_equipment_outside_the_partner_library(): void
    {
        $this->inLibrary($this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL'));
        $this->catalogExercise('ROW', 'UPPER_PULL', 'CABLE');

        $this->assertSame(['DUMBBELL'], $this->codes('?training_style=BODYBUILDING'));
    }

    public function test_filtered_list_ignores_archived_exercises(): void
    {
        $this->inLibrary($this->catalogExercise('ROW', 'UPPER_PULL', 'CABLE', null, ['archived_at' => now()]));

        $this->assertSame([], $this->codes('?training_style=BODYBUILDING'));
    }

    public function test_filtered_list_includes_trx_once_a_bodybuilding_trx_exercise_exists(): void
    {
        $trxRow = $this->inLibrary($this->catalogExercise('ROW', 'UPPER_PULL', 'TRX'));
        $this->functionalOnly($trxRow);
        $this->assertNotContains('TRX', $this->codes('?training_style=BODYBUILDING'));

        $trxRow->trainingStyles()->attach(TrainingStyle::where('code', 'BODYBUILDING')->value('id'));

        $this->assertContains('TRX', $this->codes('?training_style=BODYBUILDING'));
    }

    public function test_an_unknown_training_style_is_rejected(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/equipment-types?training_style=NOPE')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('training_style');
    }

    public function test_without_the_param_the_full_list_is_returned(): void
    {
        foreach (['DUMBBELL', 'CABLE', 'TRX'] as $code) {
            EquipmentType::firstOrCreate(['code' => $code], ['name' => $code, 'display_order' => 0]);
        }
        $this->inLibrary($this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL'));

        $this->assertEqualsCanonicalizing(['DUMBBELL', 'CABLE', 'TRX'], $this->codes());
    }
}
