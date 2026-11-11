<?php

namespace Tests\Unit\Services;

use App\Services\WorkoutGenerator\Slots\BlueprintResolver;
use App\Services\WorkoutGenerator\Slots\SessionTier;
use InvalidArgumentException;
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
            // The split seeder's "Upper Body" day: a complete upper day,
            // not a composed day with biceps but no triceps (#127).
            'push plus pull plus arms' => [['UPPER_PUSH', 'UPPER_PULL', 'ARMS'], 'UPPER'],
            'push plus pull plus arms, arms first' => [['ARMS', 'UPPER_PULL', 'UPPER_PUSH'], 'UPPER'],
            'push plus pull plus arms, pull first' => [['UPPER_PULL', 'ARMS', 'UPPER_PUSH'], 'UPPER'],
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

    /**
     * Fair trim (#127): two regions over the Express 10-set budget hold 6
     * sets each, so the tie goes against the later-requested region.
     *
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    public static function twoRegionExpressTrims(): array
    {
        return [
            'arms then lower' => [['ARMS', 'LOWER'], ['biceps', 'triceps', 'quad_compound']],
            'lower then arms' => [['LOWER', 'ARMS'], ['quad_compound', 'hinge', 'biceps']],
        ];
    }

    #[DataProvider('twoRegionExpressTrims')]
    public function test_a_two_region_tie_over_budget_trims_the_later_requested_region(array $regions, array $expectedKeys): void
    {
        $blueprint = (new BlueprintResolver)->resolve($regions, SessionTier::Express);

        $this->assertSame($expectedKeys, array_column($blueprint->slots(), 'key'));
        $this->assertSame(9, array_sum(array_column($blueprint->slots(), 'sets')));
    }

    public function test_the_region_holding_the_most_sets_loses_its_last_slot_first(): void
    {
        // Standard: ARMS 9 sets, CORE 6, UPPER_PUSH 6 = 21 > 16. ARMS holds
        // the most and loses side_delts; then all three tie at 6 and the
        // later-requested UPPER_PUSH loses secondary_press — not the
        // trailing slots of the request.
        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'CORE', 'UPPER_PUSH'], SessionTier::Standard);

        $this->assertSame(
            ['biceps', 'triceps', 'trunk_flexion', 'rotation', 'main_press'],
            array_column($blueprint->slots(), 'key'),
        );
        $this->assertSame(15, array_sum(array_column($blueprint->slots(), 'sets')));
    }

    public function test_a_four_region_express_day_keeps_every_region_until_each_is_down_to_one_slot(): void
    {
        // Express: ARMS 6, CORE 3, LOWER 6, UPPER_PUSH 3 = 18 > 10. LOWER
        // (later of the 6-set tie) loses hinge, then ARMS loses triceps;
        // with every region down to one Slot, the later-requested
        // UPPER_PUSH goes. No region empties while another keeps two.
        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'CORE', 'LOWER', 'UPPER_PUSH'], SessionTier::Express);

        $this->assertSame(
            ['biceps', 'trunk_flexion', 'quad_compound'],
            array_column($blueprint->slots(), 'key'),
        );
    }

    public function test_a_region_down_to_one_slot_keeps_it_while_another_region_has_two(): void
    {
        // Express, 13 sets > 10: a single 7-set CORE slot holds more sets
        // than ARMS's two 3-set slots, yet ARMS loses triceps: a region
        // loses its first Slot only once every region is down to one.
        config()->set('workout_blueprints.fragments.CORE.slots.0.sets', 7);

        $blueprint = (new BlueprintResolver)->resolve(['ARMS', 'CORE'], SessionTier::Express);

        $this->assertSame(['biceps', 'trunk_flexion'], array_column($blueprint->slots(), 'key'));
    }

    public function test_full_body_inside_a_larger_combination_is_refused_not_silently_dropped(): void
    {
        // FULL_BODY is requested alone or not at all (#127): validation
        // rejects a combination before it reaches the resolver, and a
        // region without a Fragment never shrinks a day unnoticed.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No fragment for region [FULL_BODY]');

        (new BlueprintResolver)->resolve(['FULL_BODY', 'ARMS'], SessionTier::Standard);
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
