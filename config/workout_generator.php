<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Movement Pattern Classification
    |--------------------------------------------------------------------------
    |
    | Compound movements are multi-joint exercises: they fill a Blueprint's
    | heavy early Slots and take compound prescriptions. Any pattern not
    | listed here is an isolation.
    |
    */

    'compound_patterns' => [
        'PRESS',
        'ROW',
        'SQUAT',
        'HINGE',
        'PULL_VERTICAL',
        'LEG_PRESS',
        'DIP',
        'LUNGE_SPLIT_SQUAT',
        'HIP_THRUST_BRIDGE',
        'PUSHUP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Prescription Matrix (ADR-0009)
    |--------------------------------------------------------------------------
    |
    | Rep ranges for a user with no history, by compound/isolation (the
    | exercise's raw movement pattern — selection aliases are gone) crossed
    | with fitness goal. Rest is the exercise's Default Rest scaled by the
    | goal's multiplier, clamped with tier-aware floors so a fat-loss deadlift
    | is never rushed, then rounded to the nearest 15s. prescription_overrides
    | is the per-pattern escape hatch; it ships empty on purpose — add entries
    | only for genuinely exceptional patterns, never to tune one exercise.
    |
    */
    'rep_ranges' => [
        'strength' => ['compound' => [3, 6], 'isolation' => [6, 10]],
        'muscle_gain' => ['compound' => [6, 10], 'isolation' => [10, 15]],
        'general_fitness' => ['compound' => [8, 12], 'isolation' => [10, 15]],
        'fat_loss' => ['compound' => [8, 12], 'isolation' => [10, 15]],
    ],

    'rest_multipliers' => [
        'strength' => 1.5,
        'muscle_gain' => 1.0,
        'general_fitness' => 0.8,
        'fat_loss' => 0.6,
    ],

    'rest_bounds' => [
        'compound_min' => 60,
        'isolation_min' => 30,
        'max' => 240,
        'round_to' => 15,
    ],

    'prescription_overrides' => [],

    /*
    |--------------------------------------------------------------------------
    | Starting Weight Ratios
    |--------------------------------------------------------------------------
    |
    | A new exercise's starting weight is body weight × its movement pattern's
    | multiplier × equipment and angle factors (ProgressionCalculatorService).
    | That fits most of the catalog but not these: a kettlebell swing is a
    | HINGE like a deadlift, a thruster a SQUAT like a back squat, a farmer's
    | walk a CARRY like an overhead march, and the core patterns start at zero
    | even when the exercise is loaded. Each ratio is the starting load per
    | implement as a fraction of body weight for an intermediate man, and
    | stands in for the pattern, equipment and angle factors; gender and
    | experience still apply. Keyed by exercise name, case-insensitively,
    | because the name is the one identifier that matches across environments.
    | Example: a 175 lb intermediate man starts kettlebell swings at
    | 79.4 kg × 0.22 = 17.5 kg, which rounds to a 16 kg kettlebell.
    |
    */
    'starting_weight_ratios' => [
        // Hinges far lighter than a deadlift (HINGE is 1.1 × body weight)
        'Machine Glute Kickback' => 0.30,
        'Kettlebell Swing' => 0.22,
        'Single Arm Kettlebell Swing' => 0.16,
        'Kettlebell Swing to Lateral Lunge' => 0.15,
        'Kettlebell Figure Eight' => 0.16,
        'Single Leg Deadlift' => 0.16,
        'Single Leg Kettlebell Deadlift' => 0.22,
        'Single-Leg Landmine Romanian Deadlift' => 0.25,
        'Dumbbell Ground to Overhead' => 0.18,
        'Power Snatch' => 0.20,

        // Squats far lighter than a back squat (SQUAT is 1.0 × body weight)
        'Dumbbell Thruster' => 0.15,
        'Thruster' => 0.15,
        'Weighted Squat Jump' => 0.07,
        'Landmine Thruster' => 0.20,
        'Landmine Squat to Press' => 0.20,

        // Other loads the pattern overstates
        'Single Leg Bridge Pullover' => 0.12,
        'Renegade Row' => 0.15,
        'Dumbbell T-Plank Row' => 0.13,

        // A carry heavier than CARRY's 0.35 × dumbbell 0.45
        "Farmer's Walk" => 0.30,

        // Loaded core work (the core patterns' multiplier is zero)
        'Ab Crunch Machine' => 0.25,
        'Cable Woodchoppers' => 0.15,
        'Landmine Rotation' => 0.10,
        'Standing Landmine Rotation' => 0.10,
        'Half-Kneeling Landmine Rotation' => 0.10,
        'Kettlebell Windmill' => 0.12,
        'Half-Kneeling Windmill' => 0.12,
        'Standing Windmill' => 0.12,
        'Kettlebell Figure 8' => 0.16,
        'Kettlebell High Plank Pull Through' => 0.10,
        'Medicine Ball Slam' => 0.08,
        'Medicine Ball Kneeling Slam' => 0.08,
        'Medicine Ball Half-Kneeling Side Slam' => 0.08,
        'Medicine Ball Standing Side Slam' => 0.08,
        'Medicine Ball Rotational Throw' => 0.08,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fitness Goal Defaults
    |--------------------------------------------------------------------------
    |
    | Default sets, reps, and rest periods based on fitness goal.
    | These are used when user has no exercise history.
    |
    */

    'fitness_goal_defaults' => [
        'strength' => [
            'sets' => 4,
            'min_reps' => 3,
            'max_reps' => 5,
            'rest_seconds' => 180,
        ],
        'muscle_gain' => [
            'sets' => 4,
            'min_reps' => 8,
            'max_reps' => 12,
            'rest_seconds' => 90,
        ],
        'fat_loss' => [
            'sets' => 3,
            'min_reps' => 12,
            'max_reps' => 15,
            'rest_seconds' => 45,
        ],
        'general_fitness' => [
            'sets' => 3,
            'min_reps' => 8,
            'max_reps' => 12,
            'rest_seconds' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Program (Mesocycle) Structure
    |--------------------------------------------------------------------------
    |
    | A generated program is one mesocycle: identical accumulation weeks ending
    | in a deload week (ADR-0007). Length is goal-driven; the deload week drops
    | every exercise to `sets`, compounds to 3-4 reps while isolations keep
    | their normal rep range, and the working weight is untouched.
    |
    */

    'program' => [
        'duration_weeks_by_goal' => [
            'fat_loss' => 6,
            'strength' => 8,
            'muscle_gain' => 8,
            'general_fitness' => 8,
        ],
        'default_duration_weeks' => 8,
        'deload' => [
            'sets' => 2,
            'compound_min_reps' => 3,
            'compound_max_reps' => 4,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Beginner Equipment Exclusions
    |--------------------------------------------------------------------------
    |
    | Equipment types that should be excluded entirely for beginner users.
    |
    */

    'beginner_excluded_equipment' => ['SMITH'],

    /*
    |--------------------------------------------------------------------------
    | Beginner Difficulty Exclusions
    |--------------------------------------------------------------------------
    |
    | Exercise difficulties excluded for beginner users (ADR-0009). An exercise
    | with no difficulty counts as intermediate and is never excluded, so the
    | gate only bites once the difficulty column is curated. Selection gates on
    | training experience, never on gender.
    |
    */
    'beginner_excluded_difficulties' => ['advanced'],
];
