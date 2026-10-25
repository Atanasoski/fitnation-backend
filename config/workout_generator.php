<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Movement Pattern Classification
    |--------------------------------------------------------------------------
    |
    | Compound movements are multi-joint exercises that should be prioritized
    | at the beginning of a workout. Isolation movements target single muscles
    | and are typically performed later in the workout.
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

    'isolation_patterns' => [
        'FLY',
        'ELBOW_FLEXION',
        'ELBOW_EXTENSION',
        'KNEE_FLEXION',
        'KNEE_EXTENSION',
        'CALF_RAISE',
        'REAR_DELT_FLY',
        'FACE_PULL',
        'SHOULDER_RAISE',
        'PULLOVER_STRAIGHT_ARM',
        'HIP_ABDUCTION',
        'BACK_EXTENSION',
        'TRUNK_FLEXION',
        'ROTATION',
    ],

    /*
    |--------------------------------------------------------------------------
    | Selection Aliases
    |--------------------------------------------------------------------------
    |
    | A movement pattern the generator selects, orders and gives sets as if it
    | were another. Lateral and front raises were filed as PRESS until
    | 2026-10-25, when they got SHOULDER_RAISE so their starting weight stops
    | being a bench press's. This alias keeps push days as they were: a raise
    | on about 80% of them, with a compound's sets. Without it, raises become
    | isolation work (2-3 sets, later in the session) on about half as many
    | push days. That is a programming decision of its own.
    |
    */
    'selection_aliases' => [
        'SHOULDER_RAISE' => 'PRESS',
    ],

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
    | Set-Based Duration Calculation
    |--------------------------------------------------------------------------
    |
    | Simple formula: 1 set = 3 minutes (universal, no variations).
    | Total sets = duration (minutes) ÷ 3.
    |
    */

    'minutes_per_set' => 3,

    /*
    |--------------------------------------------------------------------------
    | Exercise Count by Goal and Duration
    |--------------------------------------------------------------------------
    |
    | Target number of exercises based on fitness goal and workout duration.
    | Strength: fewer exercises (more sets each)
    | Muscle Gain: balanced
    | Fat Loss: more exercises (fewer sets each)
    |
    */

    'exercise_count_by_goal' => [
        'strength' => [
            30 => 4,   // 10 sets total
            45 => 4,   // 15 sets total
            60 => 5,   // 20 sets total
            90 => 7,   // 30 sets total
        ],
        'muscle_gain' => [
            30 => 4,   // 10 sets total
            45 => 5,   // 15 sets total
            60 => 6,   // 20 sets total
            90 => 8,   // 30 sets total
        ],
        'fat_loss' => [
            30 => 5,   // 10 sets total
            45 => 6,   // 15 sets total
            60 => 7,   // 20 sets total
            90 => 10,  // 30 sets total
        ],
        'general_fitness' => [
            30 => 4,   // 10 sets total
            45 => 5,   // 15 sets total
            60 => 6,   // 20 sets total
            90 => 8,   // 30 sets total
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exercise Limits
    |--------------------------------------------------------------------------
    |
    | Maximum and minimum exercises per target region, movement pattern, and total workout.
    |
    */

    'max_exercises_per_region' => 4,
    'min_exercises_per_region' => 1,
    'max_exercises_per_pattern' => 4,
    'max_total_exercises' => 12,
    'min_total_exercises' => 4,

    /*
    |--------------------------------------------------------------------------
    | Composition Rules
    |--------------------------------------------------------------------------
    |
    | Guardrails to prevent overly compound-dominant sessions.
    |
    */

    'max_compound_by_region' => [
        'LOWER' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Complementary Patterns
    |--------------------------------------------------------------------------
    |
    | When a target region is selected, we can optionally pull in complementary
    | movement patterns from other regions to round out the session.
    |
    */

    'complementary_patterns' => [
        'UPPER_PULL' => ['ELBOW_FLEXION'],
        'UPPER_PUSH' => ['ELBOW_EXTENSION'],
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
    | Set Distribution Limits
    |--------------------------------------------------------------------------
    |
    | Maximum sets per exercise type. Compounds can have more sets than isolation.
    | Every exercise gets at least min_sets_per_exercise. Until 2026-10-25 the
    | sets were handed out one exercise at a time, so the last one got what was
    | left: a 60-minute push day (20 sets) came out 4, 4, 4, 4, 3 and 1, and an
    | exercise left with none fell back to the goal's default sets, over the
    | time. The minimum wins over the time only below 24 minutes, where four
    | exercises at 2 sets need more than duration ÷ 3.
    |
    */

    'min_sets_per_exercise' => 2,
    'max_sets_per_compound' => 4,
    'max_sets_per_isolation' => 3,

    /*
    |--------------------------------------------------------------------------
    | Beginner Equipment Exclusions
    |--------------------------------------------------------------------------
    |
    | Equipment types that should be excluded entirely for beginner users.
    |
    */

    'beginner_excluded_equipment' => ['SMITH'],
];
