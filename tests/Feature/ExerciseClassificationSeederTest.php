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
 * else is BODYBUILDING.
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
            'band' => ['Band Face Pull', ['FUNCTIONAL']],
            'trx' => ['TRX Row', ['FUNCTIONAL']],
            'olympic name' => ['Barbell Push Press', ['OLYMPIC']],
            'olympic name on functional equipment' => ['Kettlebell Push Press', ['FUNCTIONAL', 'OLYMPIC']],
            'functional name' => ['Dumbbell Renegade Row', ['FUNCTIONAL']],
            'bodyweight push-up' => ['Push-ups', ['CALISTHENICS']],
            'bodyweight pull-up' => ['Pull-ups', ['CALISTHENICS']],
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
}
