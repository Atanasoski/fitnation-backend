<?php

namespace Tests\Unit\Services;

use App\Services\WorkoutGenerator\Slots\BlueprintResolver;
use App\Services\WorkoutGenerator\Slots\FragmentCompositionRequired;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins the resolver mapping table from issue #107 / ADR-0010: a request's
 * region set resolves to one Blueprint (single regions are the mobile
 * presets and map to full day types), never to an emergent composition.
 */
class BlueprintResolverTest extends TestCase
{
    /**
     * @return array<string, array{0: list<string>, 1: string}>
     */
    public static function directMappings(): array
    {
        return [
            'push preset' => [['UPPER_PUSH'], 'PUSH'],
            'push plus arms' => [['UPPER_PUSH', 'ARMS'], 'PUSH'],
            'pull preset' => [['UPPER_PULL'], 'PULL'],
            'pull plus arms' => [['UPPER_PULL', 'ARMS'], 'PULL'],
            'upper preset' => [['UPPER_PUSH', 'UPPER_PULL'], 'UPPER'],
            'lower preset' => [['LOWER'], 'LOWER'],
            'lower plus core' => [['LOWER', 'CORE'], 'LOWER'],
            'arms preset' => [['ARMS'], 'ARMS'],
        ];
    }

    #[DataProvider('directMappings')]
    public function test_region_sets_map_to_their_day_type(array $regions, string $expected): void
    {
        $blueprint = (new BlueprintResolver)->resolve($regions);

        $this->assertSame($expected, $blueprint->code);
    }

    public function test_mapping_ignores_request_order(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'UPPER_PUSH']);

        $this->assertSame('PUSH', $blueprint->code);
    }

    public function test_empty_region_list_resolves_to_full_body_with_push_focus(): void
    {
        $blueprint = (new BlueprintResolver)->resolve([]);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['push', 'delts_arms', 'lower_compound', 'pull', 'lower_iso', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_push_pull_lower_combo_resolves_to_full_body_focused_on_the_first_region(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['LOWER', 'UPPER_PUSH', 'UPPER_PULL']);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['lower_compound', 'lower_iso', 'push', 'pull', 'delts_arms', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_pull_focused_full_body_moves_the_pull_slot_first(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['UPPER_PULL', 'LOWER', 'UPPER_PUSH']);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['pull', 'lower_compound', 'push', 'lower_iso', 'delts_arms', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_all_regions_resolve_to_full_body_focused_on_the_first_region(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['UPPER_PULL', 'UPPER_PUSH', 'LOWER', 'ARMS', 'CORE']);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame('pull', $blueprint->slots()[0]->key);
    }

    /**
     * @return array<string, array{0: list<string>}>
     */
    public static function unmappedCombinations(): array
    {
        return [
            'core alone' => [['CORE']],
            'push plus lower' => [['UPPER_PUSH', 'LOWER']],
            'arms plus core' => [['ARMS', 'CORE']],
            'four regions' => [['UPPER_PUSH', 'UPPER_PULL', 'LOWER', 'ARMS']],
        ];
    }

    #[DataProvider('unmappedCombinations')]
    public function test_combinations_matching_no_blueprint_require_fragment_composition(array $regions): void
    {
        $this->expectException(FragmentCompositionRequired::class);

        (new BlueprintResolver)->resolve($regions);
    }
}
