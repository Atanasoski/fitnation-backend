<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\TargetRegion;
use App\Models\WorkoutSplit;
use Database\Seeders\TargetRegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The migration that removes the CONDITIONING target region (#127): it
 * served no exercise and no day, and declared no Fragment, so all it could
 * do was shrink a day. It refuses to run, deleting nothing, while any
 * exercise or workout split still references it.
 */
class RemoveConditioningTargetRegionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_10_210001_remove_the_conditioning_target_region.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TargetRegionSeeder::class);
        TargetRegion::create(['code' => 'CONDITIONING', 'name' => 'Conditioning', 'display_order' => 70]);
    }

    public function test_it_removes_conditioning_and_leaves_every_other_region(): void
    {
        $lower = TargetRegion::where('code', 'LOWER')->firstOrFail();
        $squat = Exercise::factory()->create(['target_region_id' => $lower->id]);
        $split = WorkoutSplit::create(['days_per_week' => 1, 'focus' => 'balanced', 'day_index' => 0, 'target_regions' => ['LOWER', 'CORE']]);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame(
            ['UPPER_PUSH', 'UPPER_PULL', 'LOWER', 'ARMS', 'CORE', 'FULL_BODY'],
            TargetRegion::orderBy('display_order')->pluck('code')->all(),
        );
        $this->assertSame($lower->id, $squat->fresh()->target_region_id);
        $this->assertSame(['LOWER', 'CORE'], $split->fresh()->target_regions);
    }

    public function test_it_refuses_while_an_exercise_references_conditioning(): void
    {
        $conditioning = TargetRegion::where('code', 'CONDITIONING')->firstOrFail();
        $burpee = Exercise::factory()->create(['target_region_id' => $conditioning->id]);

        $this->assertMigrationRefuses('1 exercise(s) and 0 workout split(s)');

        $this->assertTrue(TargetRegion::whereKey($conditioning->id)->exists());
        $this->assertSame($conditioning->id, $burpee->fresh()->target_region_id);
    }

    public function test_it_refuses_while_a_workout_split_references_conditioning(): void
    {
        $split = WorkoutSplit::create(['days_per_week' => 2, 'focus' => 'balanced', 'day_index' => 1, 'target_regions' => ['LOWER', 'CONDITIONING']]);

        $this->assertMigrationRefuses('0 exercise(s) and 1 workout split(s)');

        $this->assertTrue(TargetRegion::where('code', 'CONDITIONING')->exists());
        $this->assertSame(['LOWER', 'CONDITIONING'], $split->fresh()->target_regions);
    }

    public function test_it_can_run_twice_and_be_rolled_back(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $migration->up();

        $this->assertFalse(TargetRegion::where('code', 'CONDITIONING')->exists());

        $migration->down();
        $migration->down();

        $this->assertSame(1, TargetRegion::where('code', 'CONDITIONING')->count());
    }

    private function assertMigrationRefuses(string $expectedCounts): void
    {
        try {
            (require database_path(self::MIGRATION))->up();
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('CONDITIONING', $e->getMessage());
            $this->assertStringContainsString($expectedCounts, $e->getMessage());

            return;
        }

        $this->fail('The migration removed CONDITIONING while it was still referenced.');
    }
}
