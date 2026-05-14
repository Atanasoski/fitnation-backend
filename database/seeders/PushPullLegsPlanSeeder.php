<?php

namespace Database\Seeders;

use App\Enums\PlanType;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\TargetRegion;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Database\Seeder;

class PushPullLegsPlanSeeder extends Seeder
{
    private array $movementPatterns = [];
    private array $targetRegions = [];
    private array $equipmentTypes = [];
    private array $categories = [];
    private array $muscleGroups = [];

    public function run(): void
    {
        $this->loadLookups();

        $partner = Partner::where('slug', 'fit-nation')->first();

        if (! $partner) {
            $this->command->error('Partner "fit-nation" not found. Run PartnerSeeder first.');

            return;
        }

        $this->command->info('Seeding missing exercises...');
        $this->seedMissingExercises($partner);

        $this->command->info('Seeding 6-Day Push Pull Legs plan...');
        $this->seedPlan($partner);
    }

    private function loadLookups(): void
    {
        $this->movementPatterns = MovementPattern::pluck('id', 'code')->toArray();
        $this->targetRegions = TargetRegion::pluck('id', 'code')->toArray();
        $this->equipmentTypes = EquipmentType::pluck('id', 'code')->toArray();
        $this->categories = Category::pluck('id', 'slug')->toArray();
        $this->muscleGroups = MuscleGroup::pluck('id', 'name')->toArray();
    }

    private function mp(string $code): ?int
    {
        return $this->movementPatterns[$code] ?? null;
    }

    private function tr(string $code): ?int
    {
        return $this->targetRegions[$code] ?? null;
    }

    private function eq(string $code): ?int
    {
        return $this->equipmentTypes[$code] ?? null;
    }

    private function cat(string $slug): ?int
    {
        return $this->categories[$slug] ?? null;
    }

    private function seedMissingExercises(Partner $partner): void
    {
        $exercises = [
            [
                'name'             => 'Cable Overhead Triceps Extension',
                'category'         => 'strength-training',
                'movement_pattern' => 'ELBOW_EXTENSION',
                'target_region'    => 'ARMS',
                'equipment'        => 'CABLE',
                'default_rest_sec' => 60,
                'description'      => 'Face away from cable with rope or bar overhead, keep elbows fixed near head, extend arms fully overhead, return to a deep stretch behind the head.',
                'primary'          => ['Triceps'],
                'secondary'        => ['Core'],
            ],
            [
                'name'             => 'Dumbbell Farmers Carry',
                'category'         => 'strength-training',
                'movement_pattern' => 'CARRY',
                'target_region'    => 'ARMS',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 120,
                'description'      => 'Hold heavy dumbbells at sides, walk with tall posture and braced core for a set distance or time, set down under control.',
                'primary'          => ['Forearms'],
                'secondary'        => ['Trapezius', 'Core'],
            ],
            [
                'name'             => 'Wide-Grip Seated Cable Row',
                'category'         => 'strength-training',
                'movement_pattern' => 'ROW',
                'target_region'    => 'UPPER_PULL',
                'equipment'        => 'CABLE',
                'default_rest_sec' => 90,
                'description'      => 'Sit tall with wide bar, pull toward upper stomach flaring elbows out slightly, squeeze upper back at contraction, return under control.',
                'primary'          => ['Upper Back'],
                'secondary'        => ['Lats', 'Biceps', 'Rear Delts', 'Forearms'],
            ],
            [
                'name'             => 'Barbell Reverse Curl',
                'category'         => 'strength-training',
                'movement_pattern' => 'ELBOW_FLEXION',
                'target_region'    => 'ARMS',
                'equipment'        => 'BARBELL',
                'default_rest_sec' => 60,
                'description'      => 'Hold barbell with overhand (pronated) grip, curl to shoulder height keeping elbows fixed at sides, squeeze forearms at top, lower slowly.',
                'primary'          => ['Forearms'],
                'secondary'        => ['Biceps'],
            ],
            [
                'name'             => 'Cable Core Rotation',
                'category'         => 'strength-training',
                'movement_pattern' => 'ROTATION',
                'target_region'    => 'CORE',
                'equipment'        => 'CABLE',
                'default_rest_sec' => 60,
                'description'      => 'Stand sideways to cable at mid-height, rotate torso pulling handle across body while keeping hips square, return under control; repeat each side.',
                'primary'          => ['Obliques'],
                'secondary'        => ['Core', 'Abs'],
            ],
            [
                'name'             => 'Dumbbell Lunge',
                'category'         => 'strength-training',
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region'    => 'LOWER',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 90,
                'description'      => 'Hold dumbbells at sides, step forward lowering until back knee nears the floor, push through front foot to return to standing, alternate legs.',
                'primary'          => ['Quads', 'Glutes'],
                'secondary'        => ['Hamstrings', 'Core'],
            ],
            [
                'name'             => 'Feet Elevated Smith Machine Calf Raise',
                'category'         => 'strength-training',
                'movement_pattern' => 'CALF_RAISE',
                'target_region'    => 'LOWER',
                'equipment'        => 'SMITH',
                'default_rest_sec' => 45,
                'description'      => 'Place toes on an elevated plate under the smith bar, rise fully onto toes, pause, then lower heels below the plate for a deep calf stretch.',
                'primary'          => ['Calves'],
                'secondary'        => [],
            ],
            [
                'name'             => 'Hanging Leg Raise',
                'category'         => 'strength-training',
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region'    => 'CORE',
                'equipment'        => 'BODYWEIGHT',
                'default_rest_sec' => 60,
                'description'      => 'Hang from a pull-up bar with straight arms, raise legs to parallel or higher by flexing abs, squeeze at the top, lower slowly with control.',
                'primary'          => ['Abs'],
                'secondary'        => ['Core', 'Forearms'],
            ],
            [
                'name'             => 'Dumbbell Reverse Fly',
                'category'         => 'strength-training',
                'movement_pattern' => 'REAR_DELT_FLY',
                'target_region'    => 'UPPER_PULL',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 60,
                'description'      => 'Hinge forward with flat back, raise dumbbells out to the sides with a soft elbow bend, squeeze rear delts at the top, lower under control.',
                'primary'          => ['Rear Delts'],
                'secondary'        => ['Upper Back'],
            ],
            [
                'name'             => 'Cable Triceps Kickback',
                'category'         => 'strength-training',
                'movement_pattern' => 'ELBOW_EXTENSION',
                'target_region'    => 'ARMS',
                'equipment'        => 'CABLE',
                'default_rest_sec' => 60,
                'description'      => 'Hinge forward with upper arm parallel to floor, extend the elbow to straighten the arm back against cable resistance, squeeze triceps, return slowly.',
                'primary'          => ['Triceps'],
                'secondary'        => [],
            ],
            [
                'name'             => 'Cable Crunch',
                'category'         => 'strength-training',
                'movement_pattern' => 'TRUNK_FLEXION',
                'target_region'    => 'CORE',
                'equipment'        => 'CABLE',
                'default_rest_sec' => 60,
                'description'      => 'Kneel below a high cable with rope attachment held behind head, crunch elbows toward knees rounding the lower back, squeeze abs, return slowly.',
                'primary'          => ['Abs'],
                'secondary'        => ['Obliques', 'Core'],
            ],
            [
                'name'             => 'Barbell Curl',
                'category'         => 'strength-training',
                'movement_pattern' => 'ELBOW_FLEXION',
                'target_region'    => 'ARMS',
                'equipment'        => 'BARBELL',
                'default_rest_sec' => 60,
                'description'      => 'Stand tall with underhand grip, curl the bar to shoulder height without swinging, squeeze biceps at the top, lower slowly.',
                'primary'          => ['Biceps'],
                'secondary'        => ['Forearms'],
            ],
            [
                'name'             => 'Dumbbell Shrug',
                'category'         => 'strength-training',
                'movement_pattern' => 'CARRY',
                'target_region'    => 'ARMS',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 60,
                'description'      => 'Hold dumbbells at sides, shrug shoulders straight up toward ears, hold briefly at the top, lower with control — no rolling.',
                'primary'          => ['Trapezius'],
                'secondary'        => ['Forearms'],
            ],
            [
                'name'             => 'Dumbbell Concentration Curl',
                'category'         => 'strength-training',
                'movement_pattern' => 'ELBOW_FLEXION',
                'target_region'    => 'ARMS',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 60,
                'description'      => 'Seated with elbow braced against inner thigh, curl dumbbell to shoulder, squeeze biceps hard at top, lower slowly to full stretch.',
                'primary'          => ['Biceps'],
                'secondary'        => [],
            ],
            [
                'name'             => 'Dumbbell Side Bend',
                'category'         => 'strength-training',
                'movement_pattern' => 'ROTATION',
                'target_region'    => 'CORE',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 60,
                'description'      => 'Stand holding a dumbbell in one hand, bend laterally toward that side, return to upright using the opposite oblique; keep hips square.',
                'primary'          => ['Obliques'],
                'secondary'        => ['Core'],
            ],
            [
                'name'             => 'Bulgarian Split Squat',
                'category'         => 'strength-training',
                'movement_pattern' => 'LUNGE_SPLIT_SQUAT',
                'target_region'    => 'LOWER',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 120,
                'description'      => 'Rear foot elevated on a bench, lower front knee toward the floor keeping torso upright, drive through front foot to stand; complete reps before switching legs.',
                'primary'          => ['Quads', 'Glutes'],
                'secondary'        => ['Hamstrings', 'Core'],
            ],
            [
                'name'             => 'Barbell Hip Thrust',
                'category'         => 'strength-training',
                'movement_pattern' => 'HIP_THRUST_BRIDGE',
                'target_region'    => 'LOWER',
                'equipment'        => 'BARBELL',
                'default_rest_sec' => 120,
                'description'      => 'Upper back on a bench with barbell across hips, drive hips up to full extension squeezing glutes hard at the top, lower under control.',
                'primary'          => ['Glutes'],
                'secondary'        => ['Hamstrings', 'Core'],
            ],
            [
                'name'             => 'Lying Leg Curl',
                'category'         => 'strength-training',
                'movement_pattern' => 'KNEE_FLEXION',
                'target_region'    => 'LOWER',
                'equipment'        => 'MACHINE',
                'default_rest_sec' => 60,
                'description'      => 'Lie face down on the machine with pad above ankles, curl heels toward glutes, squeeze hamstrings at the top, lower slowly to full extension.',
                'primary'          => ['Hamstrings'],
                'secondary'        => [],
            ],
            [
                'name'             => 'Feet Elevated Dumbbell Calf Raise',
                'category'         => 'strength-training',
                'movement_pattern' => 'CALF_RAISE',
                'target_region'    => 'LOWER',
                'equipment'        => 'DUMBBELL',
                'default_rest_sec' => 45,
                'description'      => 'Stand with toes on an elevated plate holding dumbbells at sides, rise fully onto toes, lower heels below the plate for a deep stretch.',
                'primary'          => ['Calves'],
                'secondary'        => [],
            ],
            [
                'name'             => 'Machine Hip Adduction',
                'category'         => 'strength-training',
                'movement_pattern' => 'HIP_ABDUCTION',
                'target_region'    => 'LOWER',
                'equipment'        => 'MACHINE',
                'default_rest_sec' => 60,
                'description'      => 'Sit with pads on the inner knees, squeeze legs together against the resistance, pause at full adduction, return slowly without bouncing.',
                'primary'          => ['Quads'],
                'secondary'        => [],
            ],
        ];

        $newExerciseIds = [];

        foreach ($exercises as $data) {
            $exercise = Exercise::firstOrCreate(
                ['name' => $data['name']],
                [
                    'category_id'         => $this->cat($data['category']),
                    'movement_pattern_id' => $this->mp($data['movement_pattern']),
                    'target_region_id'    => $this->tr($data['target_region']),
                    'equipment_type_id'   => $this->eq($data['equipment']),
                    'angle_id'            => null,
                    'default_rest_sec'    => $data['default_rest_sec'],
                    'description'         => $data['description'],
                ]
            );

            if ($exercise->wasRecentlyCreated) {
                $attachments = [];

                foreach ($data['primary'] as $muscle) {
                    if (isset($this->muscleGroups[$muscle])) {
                        $attachments[$this->muscleGroups[$muscle]] = ['is_primary' => true];
                    }
                }

                foreach ($data['secondary'] as $muscle) {
                    if (isset($this->muscleGroups[$muscle])) {
                        $attachments[$this->muscleGroups[$muscle]] = ['is_primary' => false];
                    }
                }

                if (! empty($attachments)) {
                    $exercise->muscleGroups()->sync($attachments);
                }

                $newExerciseIds[] = $exercise->id;
                $this->command->info("  + {$exercise->name}");
            }
        }

        if (! empty($newExerciseIds)) {
            $pivotData = array_fill_keys($newExerciseIds, ['description' => null, 'image' => null, 'video' => null]);
            $partner->exercises()->syncWithoutDetaching($pivotData);
            $this->command->info('  Linked ' . count($newExerciseIds) . ' new exercise(s) to partner.');
        }
    }

    private function seedPlan(Partner $partner): void
    {
        $plan = Plan::firstOrCreate(
            ['name' => '6-Day Push Pull Legs', 'partner_id' => $partner->id],
            [
                'user_id'        => null,
                'description'    => 'A 6-day Push/Pull/Legs program with two full rotations per week. Push days target chest, shoulders and triceps; pull days target back and biceps; leg days cover the full lower body. Designed for intermediate to advanced lifters seeking complete muscle development.',
                'type'           => PlanType::Routine,
                'duration_weeks' => null,
                'is_active'      => true,
            ]
        );

        $this->command->info("Plan: {$plan->name} (id: {$plan->id})");

        $workouts = [
            [
                'name'        => 'Day 1 – Push (Chest, Shoulders & Triceps)',
                'description' => 'Primary push session built around the barbell bench press and overhead pressing.',
                'day_of_week' => 0,
                'exercises'   => [
                    ['name' => 'Barbell Bench Press',              'sets' => 4, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Dumbbell Shoulder Press',          'sets' => 3, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Pec Deck Fly',                     'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Reverse Pec Deck Fly',             'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Cable Overhead Triceps Extension', 'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Cable Lateral Raises',             'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Triceps Pushdown (Cable)',          'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                ],
            ],
            [
                'name'        => 'Day 2 – Pull (Back & Biceps)',
                'description' => 'Primary pull session with pull-ups as the main vertical pull and a farmers carry for grip.',
                'day_of_week' => 1,
                'exercises'   => [
                    ['name' => 'Wide-Grip Pull-ups',         'sets' => 4, 'min_reps' => 6,  'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Dumbbell Row',               'sets' => 3, 'min_reps' => 6,  'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Dumbbell Farmers Carry',     'sets' => 3, 'min_reps' => 0,  'max_reps' => 0,  'rest' => 150],
                    ['name' => 'Wide-Grip Seated Cable Row', 'sets' => 3, 'min_reps' => 8,  'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Barbell Reverse Curl',       'sets' => 3, 'min_reps' => 8,  'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Alternating Dumbbell Curl',  'sets' => 3, 'min_reps' => 8,  'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Cable Core Rotation',        'sets' => 3, 'min_reps' => 6,  'max_reps' => 15, 'rest' => 90],
                ],
            ],
            [
                'name'        => 'Day 3 – Legs',
                'description' => 'Lower body session built around the barbell back squat with accessory work for hamstrings and calves.',
                'day_of_week' => 2,
                'exercises'   => [
                    ['name' => 'Back Squat',                              'sets' => 4, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Romanian Deadlift',                       'sets' => 3, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Dumbbell Lunge',                          'sets' => 3, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Seated Leg Curl',                         'sets' => 3, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Feet Elevated Smith Machine Calf Raise',  'sets' => 4, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Hip Abduction Machine',                   'sets' => 3, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Hanging Leg Raise',                       'sets' => 3, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 90],
                ],
            ],
            [
                'name'        => 'Day 4 – Push (Chest, Shoulders & Triceps)',
                'description' => 'Secondary push session with incline dumbbell pressing, cable fly, and triceps finishers.',
                'day_of_week' => 3,
                'exercises'   => [
                    ['name' => 'Incline Dumbbell Bench Press', 'sets' => 4, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Skull Crushers (EZ-Bar)',      'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Cable Fly',                    'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Dumbbell Lateral Raises',      'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Dumbbell Reverse Fly',         'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Cable Triceps Kickback',       'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Cable Crunch',                 'sets' => 3, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                ],
            ],
            [
                'name'        => 'Day 5 – Pull (Back & Biceps)',
                'description' => 'Secondary pull session with supinated lat pulldown, barbell row, and dedicated bicep volume.',
                'day_of_week' => 4,
                'exercises'   => [
                    ['name' => 'Underhand Close-Grip Lat Pulldown', 'sets' => 4, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Barbell Row',                       'sets' => 4, 'min_reps' => 6, 'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Straight-Arm Cable Pulldown',       'sets' => 4, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Barbell Curl',                      'sets' => 4, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Dumbbell Shrug',                    'sets' => 4, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Dumbbell Concentration Curl',       'sets' => 4, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Dumbbell Side Bend',                'sets' => 4, 'min_reps' => 8, 'max_reps' => 15, 'rest' => 90],
                ],
            ],
            [
                'name'        => 'Day 6 – Legs',
                'description' => 'Secondary lower body session emphasising unilateral and hip-dominant movements with adduction finisher.',
                'day_of_week' => 5,
                'exercises'   => [
                    ['name' => 'Bulgarian Split Squat',             'sets' => 3, 'min_reps' => 6,  'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Barbell Hip Thrust',                'sets' => 3, 'min_reps' => 6,  'max_reps' => 15, 'rest' => 150],
                    ['name' => 'Leg Extensions',                    'sets' => 3, 'min_reps' => 8,  'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Lying Leg Curl',                    'sets' => 4, 'min_reps' => 8,  'max_reps' => 15, 'rest' => 90],
                    ['name' => 'Feet Elevated Dumbbell Calf Raise', 'sets' => 4, 'min_reps' => 8,  'max_reps' => 20, 'rest' => 90],
                    ['name' => 'Machine Hip Adduction',             'sets' => 3, 'min_reps' => 8,  'max_reps' => 15, 'rest' => 90],
                ],
            ],
        ];

        foreach ($workouts as $workout) {
            $template = WorkoutTemplate::firstOrCreate(
                ['plan_id' => $plan->id, 'name' => $workout['name']],
                [
                    'description' => $workout['description'],
                    'day_of_week' => $workout['day_of_week'],
                    'order_index' => $workout['day_of_week'],
                ]
            );

            $template->workoutTemplateExercises()->delete();

            foreach ($workout['exercises'] as $index => $exData) {
                $exercise = Exercise::whereRaw('LOWER(name) = ?', [strtolower($exData['name'])])->first();

                if (! $exercise) {
                    $this->command->warn("  Exercise not found: {$exData['name']}");

                    continue;
                }

                WorkoutTemplateExercise::create([
                    'workout_template_id' => $template->id,
                    'exercise_id'         => $exercise->id,
                    'order'               => $index + 1,
                    'target_sets'         => $exData['sets'],
                    'min_target_reps'     => $exData['min_reps'],
                    'max_target_reps'     => $exData['max_reps'],
                    'target_weight'       => 0,
                    'rest_seconds'        => $exData['rest'],
                ]);
            }

            $this->command->info("  {$template->name}");
        }

        $this->command->info('');
        $this->command->info("Done! Plan '{$plan->name}' seeded for {$partner->name}.");
    }
}
