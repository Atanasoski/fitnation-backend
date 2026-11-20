<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Database\Seeders\AngleSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\EquipmentTypeSeeder;
use Database\Seeders\ExerciseClassificationSeeder;
use Database\Seeders\MovementPatternSeeder;
use Database\Seeders\TargetRegionSeeder;
use Database\Seeders\TrainingStyleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The training styles the classification seeder tags an exercise with,
 * pinned as they stood before the inference was shared with the catalogue
 * enrichment seeder (#130). Functional equipment is FUNCTIONAL only;
 * Olympic, functional and calisthenics names add their style; anything
 * else is BODYBUILDING. ADR-0014's curated hypertrophy exceptions
 * (ALSO_BODYBUILDING) carry BODYBUILDING alongside their other style.
 *
 * Bodyweight push-ups and pull-ups being CALISTHENICS only is current
 * behaviour, a known latent problem tracked on #106; it is pinned here so
 * that changing it is a deliberate decision.
 */
class ExerciseClassificationSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function trainingStyleCases(): array
    {
        return [
            'barbell press' => ['Barbell Bench Press', ['BODYBUILDING']],
            'dumbbell isolation' => ['Dumbbell Lateral Raise', ['BODYBUILDING']],
            'kettlebell' => ['Kettlebell Goblet Squat', ['FUNCTIONAL']],
            'trx' => ['TRX Body Saw', ['FUNCTIONAL']],
            'olympic name' => ['Barbell Push Press', ['OLYMPIC']],
            'olympic name on functional equipment' => ['Kettlebell Push Press', ['FUNCTIONAL', 'OLYMPIC']],
            'functional name' => ['Dumbbell Renegade Row', ['FUNCTIONAL']],
            'bodyweight push-up' => ['Push-ups', ['CALISTHENICS']],
            'bodyweight pull-up' => ['Pull-ups', ['CALISTHENICS']],
            // ADR-0014: curated hypertrophy exceptions also carry BODYBUILDING
            'trx hypertrophy exception' => ['TRX Row', ['BODYBUILDING', 'FUNCTIONAL']],
            'band hypertrophy exception' => ['Band Face Pull', ['BODYBUILDING', 'FUNCTIONAL']],
            'functional-name exception' => ['Cossack Lunge', ['BODYBUILDING', 'FUNCTIONAL']],
            'calisthenics exception' => ['Handstand Push-ups', ['BODYBUILDING', 'CALISTHENICS']],
            'band not an exception' => ['Band Lateral Monster Walk', ['FUNCTIONAL']],
        ];
    }

    /**
     * @param  list<string>  $expectedStyles
     */
    #[DataProvider('trainingStyleCases')]
    public function test_classification_tags_the_inferred_training_styles(string $name, array $expectedStyles): void
    {
        $this->seed([
            CategorySeeder::class,
            MovementPatternSeeder::class,
            TargetRegionSeeder::class,
            EquipmentTypeSeeder::class,
            AngleSeeder::class,
            TrainingStyleSeeder::class,
        ]);
        $exercise = Exercise::factory()->create(['name' => $name]);

        $this->seed(ExerciseClassificationSeeder::class);

        $this->assertSame(
            $expectedStyles,
            $exercise->fresh()->trainingStyles->pluck('code')->sort()->values()->all(),
        );
    }

    public function test_classification_puts_goblet_squat_on_dumbbell(): void
    {
        // ADR-0014 moved it off Kettlebell; a re-seed must not undo that
        $this->seed([
            CategorySeeder::class,
            MovementPatternSeeder::class,
            TargetRegionSeeder::class,
            EquipmentTypeSeeder::class,
            AngleSeeder::class,
            TrainingStyleSeeder::class,
        ]);
        $exercise = Exercise::factory()->create(['name' => 'Goblet Squat']);

        $this->seed(ExerciseClassificationSeeder::class);

        $exercise = $exercise->fresh();
        $this->assertSame('DUMBBELL', $exercise->equipmentType->code);
        $this->assertContains('BODYBUILDING', $exercise->trainingStyles->pluck('code')->all());
    }

    public function test_the_bodybuilding_retag_lists_agree(): void
    {
        // ADR-0014: the retag lives in the seeder, exercises:import and a migration
        $migration = require database_path('migrations/2026_11_20_100000_retag_catalogue_for_bodybuilding_generation.php');
        $migrationList = (new \ReflectionClassConstant($migration, 'BODYBUILDING_ADDED'))->getValue();
        $this->assertEqualsCanonicalizing(ExerciseClassificationSeeder::ALSO_BODYBUILDING, $migrationList);

        $import = new \ReflectionMethod(\App\Console\Commands\ImportExercises::class, 'getExerciseData');
        $importData = collect($import->invoke(app(\App\Console\Commands\ImportExercises::class)))->keyBy('name');
        foreach (ExerciseClassificationSeeder::ALSO_BODYBUILDING as $name) {
            if ($importData->has($name)) {
                $this->assertContains('BODYBUILDING', $importData[$name]['training_styles'] ?? ['BODYBUILDING'], "{$name} lacks BODYBUILDING in exercises:import");
            }
        }
    }
}
