<?php

namespace Tests\Unit\Services;

use App\Services\WorkoutGenerator\Slots\BlueprintResolver;
use App\Services\WorkoutGenerator\Slots\SessionTier;
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
        $blueprint = (new BlueprintResolver)->resolve($regions, SessionTier::Standard);

        $this->assertSame($expected, $blueprint->code);
    }

    public function test_mapping_ignores_request_order(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'UPPER_PUSH'], SessionTier::Standard);

        $this->assertSame('PUSH', $blueprint->code);
    }

    public function test_empty_region_list_resolves_to_full_body_with_push_focus(): void
    {
        $blueprint = (new BlueprintResolver)->resolve([], SessionTier::Standard);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['push', 'lower_compound', 'pull', 'delts_arms', 'lower_iso', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_push_pull_lower_combo_resolves_to_full_body_focused_on_the_first_region(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], SessionTier::Standard);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['lower_compound', 'push', 'pull', 'lower_iso', 'delts_arms', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_pull_focused_full_body_moves_the_pull_slot_first(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['UPPER_PULL', 'LOWER', 'UPPER_PUSH'], SessionTier::Standard);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['pull', 'lower_compound', 'push', 'lower_iso', 'delts_arms', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_focus_reorder_never_lifts_an_isolation_slot_above_the_compounds(): void
    {
        // Push focus moves delts_arms (isolation, UPPER_PUSH) ahead of the
        // other isolations only — the heavy lifts still lead (#107 story 5).
        $blueprint = (new BlueprintResolver)->resolve([], SessionTier::Standard);

        $classes = array_map(fn ($slot) => $slot->isCompound(), $blueprint->slots());

        $this->assertSame([true, true, true, false, false, false], $classes);
    }

    public function test_the_literal_full_body_region_resolves_like_an_empty_request(): void
    {
        // ['FULL_BODY'] is user-selectable via the API but absent from
        // #107's mapping table — it means the whole-body day, so it maps
        // to the FULL_BODY blueprint with Push focus, same as no regions.
        $blueprint = (new BlueprintResolver)->resolve(['FULL_BODY'], SessionTier::Standard);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame(
            ['push', 'lower_compound', 'pull', 'delts_arms', 'lower_iso', 'core'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_all_regions_resolve_to_full_body_focused_on_the_first_region(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['UPPER_PULL', 'UPPER_PUSH', 'LOWER', 'ARMS', 'CORE'], SessionTier::Standard);

        $this->assertSame('FULL_BODY', $blueprint->code);
        $this->assertSame('pull', $blueprint->slots()[0]->key);
    }

    public function test_an_unmapped_combination_composes_its_fragments_in_request_order(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'CORE'], SessionTier::Standard);

        $this->assertTrue($blueprint->fragmentComposed);
        $this->assertSame(
            ['biceps', 'triceps', 'side_delts', 'trunk_flexion', 'rotation'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_fragment_composition_keeps_only_the_session_tiers_slots(): void
    {
        // Express keeps tier-1 only: the side_delts (T2) and rotation (T2)
        // fragment slots drop before any budget math.
        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'CORE'], SessionTier::Express);

        $this->assertSame(
            ['biceps', 'triceps', 'trunk_flexion'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_fragment_composition_trims_trailing_slots_to_the_tiers_set_budget(): void
    {
        // Standard, three regions: 7 tier-filtered slots × 3 sets = 21 >
        // the 16-set budget, so the trailing side_delts and triceps slots
        // trim — whole slots, from the end, never from the front.
        $blueprint = (new BlueprintResolver)->resolve(['UPPER_PUSH', 'UPPER_PULL', 'ARMS'], SessionTier::Standard);

        $this->assertSame(
            ['main_press', 'secondary_press', 'vertical_pull', 'row', 'biceps'],
            array_column($blueprint->slots(), 'key'),
        );
        $this->assertSame(15, array_sum(array_column($blueprint->slots(), 'sets')));
    }

    public function test_a_region_with_no_fragment_contributes_no_slots(): void
    {
        // CONDITIONING has no Fragment by design (#107): it adds nothing
        // to a combination, and alone it composes an empty day for the
        // generator to refuse with its ordinary "no exercises" error.
        $withCore = (new BlueprintResolver)->resolve(['CORE', 'CONDITIONING'], SessionTier::Standard);
        $this->assertSame(['trunk_flexion', 'rotation'], array_column($withCore->slots(), 'key'));

        $alone = (new BlueprintResolver)->resolve(['CONDITIONING'], SessionTier::Standard);
        $this->assertTrue($alone->fragmentComposed);
        $this->assertSame([], $alone->slots());
    }

    public function test_fragment_composition_follows_the_request_order_not_a_canonical_one(): void
    {
        $blueprint = (new BlueprintResolver)->resolve(['CORE', 'ARMS'], SessionTier::Standard);

        $this->assertSame(
            ['trunk_flexion', 'rotation', 'biceps', 'triceps', 'side_delts'],
            array_column($blueprint->slots(), 'key'),
        );
    }
}
