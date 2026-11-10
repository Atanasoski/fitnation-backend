<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\User;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use Database\Seeders\CatalogEnrichmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The catalogue enrichment imports at the generator seam (#130): seeded into
 * the House Partner's library, they reach the House member whose equipment
 * they are for, and never a full-gym member on the default style. Bands and
 * kettlebells are FUNCTIONAL only, so the default BODYBUILDING day never
 * draws them: the imports' only flat presses are a band and a kettlebell
 * press, so a push day would otherwise always open with one.
 */
class CatalogEnrichmentGenerationTest extends TestCase
{
    use RefreshDatabase;

    private BlueprintWorkoutGenerator $generator;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);

        $this->seed(CatalogEnrichmentSeeder::class);

        $this->generator = app(BlueprintWorkoutGenerator::class);

        $this->member = User::factory()->create(['partner_id' => $house->id]);
        $this->member->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);
    }

    /**
     * @return array<string, array{0: list<string>}>
     */
    public static function dayRequests(): array
    {
        return [
            'push' => [['UPPER_PUSH']],
            'pull' => [['UPPER_PULL']],
            'upper' => [['UPPER_PUSH', 'UPPER_PULL']],
            'lower' => [['LOWER']],
            'arms' => [['ARMS']],
        ];
    }

    /**
     * @param  list<string>  $regions
     */
    #[DataProvider('dayRequests')]
    public function test_a_full_gym_member_on_the_default_style_never_receives_a_band_or_kettlebell_import(array $regions): void
    {
        $equipment = $this->equipmentOfGeneratedRows([
            'target_regions' => $regions,
            'duration_minutes' => 90,
        ]);

        $this->assertNotEmpty($equipment);
        $this->assertNotContains('BAND', $equipment);
        $this->assertNotContains('KETTLEBELL', $equipment);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function functionalEquipment(): array
    {
        return [
            'band only' => ['BAND'],
            'kettlebell only' => ['KETTLEBELL'],
        ];
    }

    #[DataProvider('functionalEquipment')]
    public function test_a_member_with_only_functional_equipment_receives_its_imports(string $equipmentCode): void
    {
        $equipment = $this->equipmentOfGeneratedRows([
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 60,
            'equipment_types' => [$equipmentCode],
            'training_styles' => ['BODYBUILDING'],
        ]);

        $this->assertContains($equipmentCode, $equipment);
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @return list<string>
     */
    private function equipmentOfGeneratedRows(array $preferences): array
    {
        $rows = $this->generator->generate($this->member, $preferences)['exercises'];

        return Exercise::query()
            ->with('equipmentType')
            ->findMany(array_column($rows, 'exercise_id'))
            ->map(fn (Exercise $exercise) => $exercise->equipmentType->code)
            ->values()
            ->all();
    }
}
