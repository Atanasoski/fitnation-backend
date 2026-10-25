<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\MovementPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The migration that gives lateral and front raises their own movement
 * pattern: raises still filed as PRESS move to SHOULDER_RAISE, and nothing
 * else moves.
 */
class ShoulderRaiseMovementPatternMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_25_000001_add_the_shoulder_raise_movement_pattern.php';

    public function test_raises_filed_as_press_move_to_shoulder_raise(): void
    {
        $press = MovementPattern::firstOrCreate(['code' => 'PRESS'], ['name' => 'Press', 'display_order' => 10]);
        $rearDeltFly = MovementPattern::firstOrCreate(['code' => 'REAR_DELT_FLY'], ['name' => 'Rear Delt Fly', 'display_order' => 150]);

        $lateral = Exercise::factory()->create(['name' => 'Cable Lateral Raises', 'movement_pattern_id' => $press->id]);
        $front = Exercise::factory()->create(['name' => 'Plate Front Raises', 'movement_pattern_id' => $press->id]);
        $bench = Exercise::factory()->create(['name' => 'Barbell Bench Press', 'movement_pattern_id' => $press->id]);
        $rearRaise = Exercise::factory()->create(['name' => 'Bent-Over Rear Lateral Raise', 'movement_pattern_id' => $press->id]);
        $staffFiled = Exercise::factory()->create(['name' => 'Machine Lateral Raise', 'movement_pattern_id' => $rearDeltFly->id]);

        (require database_path(self::MIGRATION))->up();

        $raise = MovementPattern::where('code', 'SHOULDER_RAISE')->firstOrFail();
        $this->assertSame($raise->id, $lateral->fresh()->movement_pattern_id);
        $this->assertSame($raise->id, $front->fresh()->movement_pattern_id);
        $this->assertSame($press->id, $bench->fresh()->movement_pattern_id);
        $this->assertSame($press->id, $rearRaise->fresh()->movement_pattern_id);
        $this->assertSame($rearDeltFly->id, $staffFiled->fresh()->movement_pattern_id);
    }

    public function test_it_can_run_twice_and_be_rolled_back(): void
    {
        $press = MovementPattern::firstOrCreate(['code' => 'PRESS'], ['name' => 'Press', 'display_order' => 10]);
        $lateral = Exercise::factory()->create(['name' => 'Dumbbell Lateral Raises', 'movement_pattern_id' => $press->id]);

        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $migration->up();

        $this->assertSame(1, MovementPattern::where('code', 'SHOULDER_RAISE')->count());

        $migration->down();

        $this->assertSame($press->id, $lateral->fresh()->movement_pattern_id);
        $this->assertFalse(MovementPattern::where('code', 'SHOULDER_RAISE')->exists());
    }
}
