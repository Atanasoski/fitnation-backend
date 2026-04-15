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
        'PULLOVER_STRAIGHT_ARM',
        'HIP_ABDUCTION',
        'BACK_EXTENSION',
        'TRUNK_FLEXION',
        'ROTATION',
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
    |
    */

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
