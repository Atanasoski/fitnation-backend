<?php

/*
|--------------------------------------------------------------------------
| Workout Blueprints (ADR-0010, issue #107)
|--------------------------------------------------------------------------
|
| The declared shape of every generated workout day. Each Blueprint is an
| ordered list of Slots; each Slot is a movement role (one or more
| pattern + optional-angles alternatives) carrying a Tier, a set count,
| and a rep range. A day's composition is read off its Blueprint, never
| emergent from selection rules. These tables are the ones signed off in
| issue #107 — tuning a slot is a config PR that also updates
| tests/Unit/Services/WorkoutBlueprintConfigTest.php.
|
| Slot fields:
| - key:     stable identifier, stored on generated rows as slot_key
| - tier:    1 = Express+, 2 = Standard+, 3 = Extended only
| - sets:    standard set count (Extended adds +1 to the first two
|            tier-1 slots at assembly time)
| - reps:    [min, max] rep range before goal shifting
| - regions: target-region code(s) the role lives in — used by the
|            Relaxation Waterfall when the pattern is dropped
| - roles:   alternatives; an exercise satisfies the slot when its
|            movement pattern matches a role's pattern and, if the role
|            lists angles, its angle is one of them (a null-angle
|            exercise matches any angle requirement, mirroring the
|            selector convention)
|
| Compound/isolation class is derived from the slot's first pattern via
| config/workout_generator.php compound_patterns — not redeclared here.
*/

return [

    'blueprints' => [

        'PUSH' => [
            'slots' => [
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
            ],
        ],

        'PULL' => [
            'slots' => [
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
            ],
        ],

        // Express upper is deliberately slightly push-biased (two presses +
        // one pull among its tier-1 slots) — accepted in #107.
        'UPPER' => [
            'slots' => [
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
            ],
        ],

        'LOWER' => [
            'slots' => [
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
            ],
        ],

        'FULL_BODY' => [
            'slots' => [
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
            ],
        ],

        // Two curls / two extensions on an Arms day is by design (heavy +
        // finisher slots); the old per-pattern cap overrides die with #113.
        'ARMS' => [
            'slots' => [
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
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Fragments
    |--------------------------------------------------------------------------
    |
    | A target region's short, ordered Slot list, used to compose a day for a
    | custom region combination that matches no Blueprint (API requests and
    | admin-defined split days; mobile presets can't produce one). Every
    | requestable region except FULL_BODY (requested alone only) declares
    | one. Fragments are concatenated in request order, tier-filtered, and
    | trimmed fairly to the tier's set budget (fragment_set_budgets below):
    | the region holding the most sets loses its last slot (ties: the
    | later-requested region), and a region loses its first slot only once
    | every region is down to one.
    */

    'fragments' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Fragment set budgets
    |--------------------------------------------------------------------------
    |
    | Total-set ceiling per session tier for fragment-composed days
    | (~9–10 / ~15–16 / ~20–22 in #107).
    */

    'fragment_set_budgets' => [
        'express' => 10,
        'standard' => 16,
        'extended' => 22,
    ],

];
