<?php

namespace Tests\Feature;

use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\TargetRegion;
use App\Services\Exercise\ExerciseGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin exercise gallery's filters and facet counts (spec 023, ticket 03).
 */
class ExerciseGalleryTest extends TestCase
{
    use RefreshDatabase;

    private TargetRegion $upper;

    private TargetRegion $lower;

    private EquipmentType $barbell;

    private EquipmentType $dumbbell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->upper = TargetRegion::create(['code' => 'UPPER', 'name' => 'Upper', 'display_order' => 1]);
        $this->lower = TargetRegion::create(['code' => 'LOWER', 'name' => 'Lower', 'display_order' => 2]);
        $this->barbell = EquipmentType::create(['code' => 'BARBELL', 'name' => 'Barbell', 'display_order' => 1]);
        $this->dumbbell = EquipmentType::create(['code' => 'DUMBBELL', 'name' => 'Dumbbell', 'display_order' => 2]);

        $this->exercise('Bench press', $this->upper, $this->barbell, 'beginner');
        $this->exercise('Dumbbell fly', $this->upper, $this->dumbbell, 'advanced');
        $this->exercise('Back squat', $this->lower, $this->barbell, 'beginner');
        $this->exercise('Old press', $this->upper, $this->barbell, 'beginner', ['archived_at' => now()]);
    }

    public function test_with_no_filters_it_lists_every_exercise_that_is_not_archived(): void
    {
        $this->assertNames(['Back squat', 'Bench press', 'Dumbbell fly'], ExerciseGallery::fromQuery([]));
    }

    public function test_filters_narrow_and_each_facet_counts_under_the_other_filters(): void
    {
        $gallery = ExerciseGallery::fromQuery(['region' => [$this->upper->id]]);

        $this->assertNames(['Bench press', 'Dumbbell fly'], $gallery);

        $facets = $gallery->facets();
        $this->assertSame([$this->upper->id => 2, $this->lower->id => 1], $this->counts($facets['region']));
        $this->assertSame([$this->barbell->id => 1, $this->dumbbell->id => 1], $this->counts($facets['equipment']));
        $this->assertSame(['beginner' => 1, 'advanced' => 1], $this->counts($facets['difficulty']));
    }

    public function test_two_facets_together(): void
    {
        $gallery = ExerciseGallery::fromQuery([
            'region' => [$this->upper->id],
            'equipment' => [$this->barbell->id],
        ]);

        $this->assertNames(['Bench press'], $gallery);
        $this->assertSame([$this->upper->id => 1, $this->lower->id => 1], $this->counts($gallery->facets()['region']));
        $this->assertSame([$this->barbell->id => 1, $this->dumbbell->id => 1], $this->counts($gallery->facets()['equipment']));
    }

    public function test_several_values_of_one_facet_widen_it(): void
    {
        $gallery = ExerciseGallery::fromQuery(['difficulty' => ['beginner', 'advanced']]);

        $this->assertNames(['Back squat', 'Bench press', 'Dumbbell fly'], $gallery);
    }

    public function test_missing_media_keeps_exercises_without_an_image_a_video_or_a_muscle_group_image(): void
    {
        $this->exercise('Complete', $this->upper, $this->barbell, 'beginner', [
            'image' => 'a.jpg', 'video' => 'a.mp4', 'muscle_group_image' => 'm.png',
        ]);
        $this->exercise('No video', $this->upper, $this->barbell, 'beginner', [
            'image' => 'b.jpg', 'muscle_group_image' => 'm.png',
        ]);

        $gallery = ExerciseGallery::fromQuery(['missing' => 'media']);

        $this->assertNames(['Back squat', 'Bench press', 'Dumbbell fly', 'No video'], $gallery);
        $this->assertSame(4, ExerciseGallery::fromQuery([])->facets()['missing']);
    }

    public function test_archived_shows_only_archived_exercises(): void
    {
        $gallery = ExerciseGallery::fromQuery(['archived' => '1']);

        $this->assertNames(['Old press'], $gallery);
        $this->assertSame(1, ExerciseGallery::fromQuery([])->facets()['archived']);
    }

    public function test_search_matches_every_word_of_the_name(): void
    {
        $this->assertNames(['Bench press'], ExerciseGallery::fromQuery(['q' => 'press bench']));
    }

    public function test_the_query_round_trips_and_drops_what_it_does_not_know(): void
    {
        $query = [
            'q' => 'press',
            'region' => [(string) $this->upper->id],
            'equipment' => [(string) $this->barbell->id],
            'difficulty' => ['beginner'],
            'missing' => 'media',
            'archived' => '1',
        ];

        $gallery = ExerciseGallery::fromQuery($query + ['edit' => '5', 'junk' => 'x', 'difficulty_bogus' => 'y']);

        $this->assertEquals($query, $gallery->query());
        $this->assertEquals($query, ExerciseGallery::fromQuery($gallery->query())->query());
        $this->assertSame([], ExerciseGallery::fromQuery(['difficulty' => ['elite'], 'region' => ['abc']])->query());
    }

    public function test_toggling_a_filter_value_adds_or_removes_only_that_value(): void
    {
        $gallery = ExerciseGallery::fromQuery(['q' => 'press', 'region' => [$this->upper->id], 'missing' => 'media']);

        $this->assertTrue($gallery->has('region', $this->upper->id));
        $this->assertFalse($gallery->has('region', $this->lower->id));
        $this->assertEquals(
            ['q' => 'press', 'region' => [(string) $this->upper->id, (string) $this->lower->id], 'missing' => 'media'],
            $gallery->toggled('region', $this->lower->id),
        );
        $this->assertEquals(['q' => 'press', 'missing' => 'media'], $gallery->toggled('region', $this->upper->id));
        $this->assertEquals(['q' => 'press', 'region' => [(string) $this->upper->id]], $gallery->toggled('missing', 'media'));
        $this->assertEquals(
            ['q' => 'press', 'region' => [(string) $this->upper->id], 'missing' => 'media', 'archived' => '1'],
            $gallery->toggled('archived', '1'),
        );
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertNames(array $expected, ExerciseGallery $gallery): void
    {
        $this->assertSame($expected, $gallery->exercises()->getCollection()->pluck('name')->sort()->values()->all());
    }

    /**
     * Facet options with a count, keyed by value.
     *
     * @param  iterable<array{value: int|string, label: string, count: int}>  $facet
     * @return array<int|string, int>
     */
    private function counts(iterable $facet): array
    {
        return collect($facet)->filter(fn ($option) => $option['count'] > 0)
            ->mapWithKeys(fn ($option) => [$option['value'] => $option['count']])->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function exercise(string $name, TargetRegion $region, EquipmentType $equipment, string $difficulty, array $attributes = []): Exercise
    {
        return Exercise::factory()->create($attributes + [
            'name' => $name,
            'target_region_id' => $region->id,
            'equipment_type_id' => $equipment->id,
            'difficulty' => $difficulty,
        ]);
    }
}
