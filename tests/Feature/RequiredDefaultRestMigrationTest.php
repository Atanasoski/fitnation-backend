<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that makes Default Rest required: every Exercise has one
 * (GLOSSARY). An exercise still without one gets 90s, the admin form's and
 * the importer's default, before the column stops accepting NULL; curated
 * values stay. Schema changes commit MySQL's open transaction, so this class
 * runs outside one, removes the rows it wrote and leaves the column migrated.
 */
class RequiredDefaultRestMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_10_220000_make_the_default_rest_of_every_exercise_required.php';

    /**
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    private function defaultRestIsNullable(): bool
    {
        return collect(Schema::getColumns('workout_exercises'))
            ->firstWhere('name', 'default_rest_sec')['nullable'];
    }

    private function insertExercise(string $name, ?int $defaultRestSec): int
    {
        return DB::table('workout_exercises')->insertGetId([
            'name' => $name,
            'default_rest_sec' => $defaultRestSec,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function defaultRestOf(int $exerciseId): ?int
    {
        $value = DB::table('workout_exercises')->where('id', $exerciseId)->value('default_rest_sec');

        return $value === null ? null : (int) $value;
    }

    public function test_an_exercise_without_a_default_rest_gets_ninety_seconds_and_the_column_stops_accepting_null(): void
    {
        $migration = require database_path(self::MIGRATION);
        $ids = [];

        try {
            $migration->down();
            $this->assertTrue($this->defaultRestIsNullable());

            $ids[] = $missing = $this->insertExercise('Rest-less Row', null);
            $ids[] = $curated = $this->insertExercise('Curated Squat', 150);

            $migration->up();

            $this->assertSame(90, $this->defaultRestOf($missing));
            $this->assertSame(150, $this->defaultRestOf($curated));
            $this->assertFalse($this->defaultRestIsNullable());

            try {
                DB::table('workout_exercises')->where('id', $curated)->update(['default_rest_sec' => null]);
                $this->fail('A NULL Default Rest was stored');
            } catch (QueryException) {
                $this->assertSame(150, $this->defaultRestOf($curated));
            }
        } finally {
            DB::table('workout_exercises')->whereIn('id', $ids)->delete();
            if ($this->defaultRestIsNullable()) {
                $migration->up();
            }
        }
    }
}
