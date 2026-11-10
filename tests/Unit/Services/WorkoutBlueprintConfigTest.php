<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins config/workout_blueprints.php to the slot tables signed off in
 * issue #107 / ADR-0010. A change here is a programming decision and must
 * arrive as a reviewed config PR that updates both sides.
 */
class WorkoutBlueprintConfigTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array<int, array<string, mixed>>}>
     */
    public static function blueprintTables(): array
    {
        return [
            'PUSH' => ['PUSH', [
                ['key' => 'main_press', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['FLAT', 'HORIZONTAL']],
                    ['pattern' => 'PUSHUP'],
                ]],
                ['key' => 'incline_press', 'tier' => 2, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['INCLINE']],
                ]],
                ['key' => 'overhead_press', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['VERTICAL']],
                ]],
                ['key' => 'side_delts', 'tier' => 2, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'SHOULDER_RAISE'],
                ]],
                ['key' => 'triceps', 'tier' => 1, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_EXTENSION'],
                ]],
                ['key' => 'chest_iso', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'FLY'],
                ]],
            ]],
            'PULL' => ['PULL', [
                ['key' => 'vertical_pull', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'PULL_VERTICAL'],
                ]],
                ['key' => 'heavy_row', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'ROW', 'angles' => ['HORIZONTAL']],
                ]],
                ['key' => 'second_row', 'tier' => 2, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'ROW', 'angles' => ['HORIZONTAL']],
                    ['pattern' => 'PULL_VERTICAL'],
                ]],
                ['key' => 'rear_delts', 'tier' => 2, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'REAR_DELT_FLY'],
                    ['pattern' => 'FACE_PULL'],
                ]],
                ['key' => 'biceps', 'tier' => 1, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_FLEXION'],
                ]],
                ['key' => 'lats_iso', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'PULLOVER_STRAIGHT_ARM'],
                ]],
            ]],
            'UPPER' => ['UPPER', [
                ['key' => 'chest_press', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['FLAT', 'HORIZONTAL']],
                    ['pattern' => 'PUSHUP'],
                ]],
                ['key' => 'heavy_pull', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'ROW', 'angles' => ['HORIZONTAL']],
                    ['pattern' => 'PULL_VERTICAL'],
                ]],
                ['key' => 'press_2', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['INCLINE', 'VERTICAL']],
                ]],
                ['key' => 'second_pull', 'tier' => 2, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'ROW', 'angles' => ['HORIZONTAL']],
                    ['pattern' => 'PULL_VERTICAL'],
                ]],
                ['key' => 'side_delts', 'tier' => 2, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'SHOULDER_RAISE'],
                ]],
                ['key' => 'arms', 'tier' => 3, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_EXTENSION'],
                    ['pattern' => 'ELBOW_FLEXION'],
                ]],
            ]],
            'LOWER' => ['LOWER', [
                ['key' => 'quad_compound', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'SQUAT'],
                    ['pattern' => 'LEG_PRESS'],
                ]],
                ['key' => 'hinge', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'HINGE'],
                    ['pattern' => 'HIP_THRUST_BRIDGE'],
                ]],
                ['key' => 'quad_iso', 'tier' => 2, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'KNEE_EXTENSION'],
                    ['pattern' => 'LUNGE_SPLIT_SQUAT'],
                ]],
                ['key' => 'ham_iso', 'tier' => 1, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'KNEE_FLEXION'],
                ]],
                ['key' => 'calves', 'tier' => 2, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'CALF_RAISE'],
                ]],
                ['key' => 'abs', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['CORE'], 'roles' => [
                    ['pattern' => 'TRUNK_FLEXION'],
                    ['pattern' => 'ROTATION'],
                ]],
            ]],
            'FULL_BODY' => ['FULL_BODY', [
                ['key' => 'lower_compound', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'SQUAT'],
                    ['pattern' => 'LEG_PRESS'],
                    ['pattern' => 'HINGE'],
                ]],
                ['key' => 'push', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['FLAT', 'HORIZONTAL', 'INCLINE']],
                    ['pattern' => 'PUSHUP'],
                ]],
                ['key' => 'pull', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'ROW', 'angles' => ['HORIZONTAL']],
                    ['pattern' => 'PULL_VERTICAL'],
                ]],
                ['key' => 'lower_iso', 'tier' => 2, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'KNEE_FLEXION'],
                    ['pattern' => 'KNEE_EXTENSION'],
                ]],
                ['key' => 'delts_arms', 'tier' => 2, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['UPPER_PUSH', 'ARMS'], 'roles' => [
                    ['pattern' => 'SHOULDER_RAISE'],
                    ['pattern' => 'ELBOW_FLEXION'],
                    ['pattern' => 'ELBOW_EXTENSION'],
                ]],
                ['key' => 'core', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['CORE'], 'roles' => [
                    ['pattern' => 'TRUNK_FLEXION'],
                    ['pattern' => 'ROTATION'],
                ]],
            ]],
            'ARMS' => ['ARMS', [
                ['key' => 'overhead_press', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['VERTICAL']],
                ]],
                ['key' => 'biceps_heavy', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_FLEXION'],
                ]],
                ['key' => 'triceps_heavy', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_EXTENSION'],
                ]],
                ['key' => 'side_delts', 'tier' => 2, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'SHOULDER_RAISE'],
                ]],
                ['key' => 'arms_finisher', 'tier' => 2, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_FLEXION'],
                    ['pattern' => 'ELBOW_EXTENSION'],
                ]],
                ['key' => 'rear_delts', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'REAR_DELT_FLY'],
                    ['pattern' => 'FACE_PULL'],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $expectedSlots
     */
    #[DataProvider('blueprintTables')]
    public function test_blueprint_matches_signed_off_slot_table(string $code, array $expectedSlots): void
    {
        $blueprints = config('workout_blueprints.blueprints');

        $this->assertIsArray($blueprints, 'config/workout_blueprints.php must define blueprints');
        $this->assertArrayHasKey($code, $blueprints);
        $this->assertSame($expectedSlots, $blueprints[$code]['slots']);
    }

    public function test_exactly_the_six_signed_off_blueprints_exist(): void
    {
        $this->assertSame(
            ['PUSH', 'PULL', 'UPPER', 'LOWER', 'FULL_BODY', 'ARMS'],
            array_keys(config('workout_blueprints.blueprints', []))
        );
    }

    public function test_fragments_match_signed_off_tables(): void
    {
        $expected = [
            'UPPER_PUSH' => [
                ['key' => 'main_press', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['FLAT', 'HORIZONTAL']],
                    ['pattern' => 'PUSHUP'],
                ]],
                ['key' => 'secondary_press', 'tier' => 2, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'PRESS', 'angles' => ['INCLINE', 'VERTICAL']],
                ]],
                ['key' => 'chest_iso', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'FLY'],
                ]],
            ],
            'UPPER_PULL' => [
                ['key' => 'vertical_pull', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'PULL_VERTICAL'],
                ]],
                ['key' => 'row', 'tier' => 2, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'ROW', 'angles' => ['HORIZONTAL']],
                ]],
                ['key' => 'rear_delts', 'tier' => 3, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PULL'], 'roles' => [
                    ['pattern' => 'REAR_DELT_FLY'],
                    ['pattern' => 'FACE_PULL'],
                ]],
            ],
            'LOWER' => [
                ['key' => 'quad_compound', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'SQUAT'],
                    ['pattern' => 'LEG_PRESS'],
                ]],
                ['key' => 'hinge', 'tier' => 1, 'sets' => 3, 'reps' => [6, 10], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'HINGE'],
                    ['pattern' => 'HIP_THRUST_BRIDGE'],
                ]],
                ['key' => 'ham_iso', 'tier' => 2, 'sets' => 3, 'reps' => [10, 15], 'regions' => ['LOWER'], 'roles' => [
                    ['pattern' => 'KNEE_FLEXION'],
                ]],
            ],
            'ARMS' => [
                ['key' => 'biceps', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_FLEXION'],
                ]],
                ['key' => 'triceps', 'tier' => 1, 'sets' => 3, 'reps' => [8, 12], 'regions' => ['ARMS'], 'roles' => [
                    ['pattern' => 'ELBOW_EXTENSION'],
                ]],
                ['key' => 'side_delts', 'tier' => 2, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['UPPER_PUSH'], 'roles' => [
                    ['pattern' => 'SHOULDER_RAISE'],
                ]],
            ],
            'CORE' => [
                ['key' => 'trunk_flexion', 'tier' => 1, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['CORE'], 'roles' => [
                    ['pattern' => 'TRUNK_FLEXION'],
                ]],
                ['key' => 'rotation', 'tier' => 2, 'sets' => 3, 'reps' => [12, 15], 'regions' => ['CORE'], 'roles' => [
                    ['pattern' => 'ROTATION'],
                    ['pattern' => 'ANTI_ROTATION'],
                ]],
            ],
        ];

        $this->assertSame($expected, config('workout_blueprints.fragments'));
    }

    public function test_fragment_set_budgets_match_spec(): void
    {
        $this->assertSame(
            ['express' => 10, 'standard' => 16, 'extended' => 22],
            config('workout_blueprints.fragment_set_budgets')
        );
    }

    public function test_every_slot_region_and_pattern_is_a_known_catalog_code(): void
    {
        $knownRegions = ['UPPER_PUSH', 'UPPER_PULL', 'LOWER', 'ARMS', 'CORE', 'FULL_BODY', 'CONDITIONING'];

        // The movement_patterns catalog codes, listed here rather than read
        // from config/workout_generator.php: its isolation_patterns key was
        // dead surface, deleted with the legacy generator (ADR-0010).
        $knownPatterns = [
            'ANTI_ROTATION', 'BACK_EXTENSION', 'CALF_RAISE', 'CARRY', 'DIP',
            'ELBOW_EXTENSION', 'ELBOW_FLEXION', 'FACE_PULL', 'FLY', 'HINGE',
            'HIP_ABDUCTION', 'HIP_THRUST_BRIDGE', 'KNEE_EXTENSION', 'KNEE_FLEXION',
            'LEG_PRESS', 'LUNGE_SPLIT_SQUAT', 'PRESS', 'PULL_VERTICAL',
            'PULLOVER_STRAIGHT_ARM', 'PUSHUP', 'REAR_DELT_FLY', 'ROTATION',
            'ROW', 'SHOULDER_RAISE', 'SQUAT', 'TRUNK_FLEXION',
        ];

        $slotLists = array_merge(
            array_map(fn (array $b) => $b['slots'], config('workout_blueprints.blueprints')),
            config('workout_blueprints.fragments')
        );

        foreach ($slotLists as $slots) {
            foreach ($slots as $slot) {
                foreach ($slot['regions'] as $region) {
                    $this->assertContains($region, $knownRegions, "slot {$slot['key']}");
                }
                foreach ($slot['roles'] as $role) {
                    $this->assertContains($role['pattern'], $knownPatterns, "slot {$slot['key']}");
                }
            }
        }
    }
}
