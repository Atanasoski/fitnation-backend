<?php

namespace Tests\Feature;

use App\Http\Resources\Api\EquipmentTypeResource;
use App\Models\EquipmentType;
use Database\Seeders\EquipmentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whether an exercise takes a logged weight is decided here, once, per
 * equipment type — not by each client (app spec 0015). TRX is the case that
 * drifted: one screen refused a weight, the next accepted one.
 */
class EquipmentTypeSupportsAddedWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seed_marks_bodyweight_trx_and_band_as_carrying_no_added_weight(): void
    {
        $this->seed(EquipmentTypeSeeder::class);

        $flags = EquipmentType::query()->pluck('supports_added_weight', 'code')->all();

        $this->assertSame(false, $flags['BODYWEIGHT']);
        $this->assertSame(false, $flags['TRX']);
        $this->assertSame(false, $flags['BAND']);
        foreach (['BARBELL', 'DUMBBELL', 'CABLE', 'MACHINE', 'SMITH', 'KETTLEBELL', 'MEDICINE_BALL', 'LANDMINE'] as $code) {
            $this->assertSame(true, $flags[$code], $code);
        }
    }

    public function test_the_resource_exposes_the_flag(): void
    {
        $trx = EquipmentType::create(['code' => 'TRX', 'name' => 'TRX', 'display_order' => 90, 'supports_added_weight' => false]);
        // fresh(): a just-created model has not read the column default back yet.
        $barbell = EquipmentType::create(['code' => 'BARBELL', 'name' => 'Barbell', 'display_order' => 10])->fresh();

        $this->assertSame(false, (new EquipmentTypeResource($trx))->toArray(request())['supports_added_weight']);
        $this->assertSame(true, (new EquipmentTypeResource($barbell))->toArray(request())['supports_added_weight']);
    }

    public function test_a_new_equipment_type_carries_added_weight_unless_said_otherwise(): void
    {
        $type = EquipmentType::create(['code' => 'SANDBAG', 'name' => 'Sandbag', 'display_order' => 120]);

        $this->assertTrue($type->fresh()->supports_added_weight);
    }
}
