<?php

namespace Database\Seeders;

use App\Enums\PlanType;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a 2-day circuit program ("Trening 1" & "Trening 2") for a specific user.
 *
 * Idempotent:
 *  - Missing catalogue exercises are created via firstOrCreate (by name).
 *  - The Plan is matched by (user_id, name) and its templates are rebuilt each run.
 *
 * Run:  php artisan db:seed --class=CstefanTreningProgramSeeder
 */
class CstefanTreningProgramSeeder extends Seeder
{
    private const USER_EMAIL = 'cstefan1991@gmail.com';

    private const PLAN_NAME = 'Trening Program';

    /**
     * Exercises that are NOT yet in the catalogue and must be created.
     * category_id / equipment_type_id resolved from existing lookup tables:
     *   categories: 1 Strength, 2 Functional Training, 6 Bodyweight
     *   equipment_types: 2 Dumbbell, 6 Bodyweight
     */
    private const NEW_EXERCISES = [
        'Bench Dips' => [
            'category_id' => 6, 'equipment_type_id' => 6, 'default_rest_sec' => 60,
            'description' => 'Bodyweight triceps dips performed with hands on a bench and feet on the floor.',
        ],
        'Dumbbell Sumo Squat' => [
            'category_id' => 1, 'equipment_type_id' => 2, 'default_rest_sec' => 60,
            'description' => 'Wide-stance squat holding a single dumbbell between the legs.',
        ],
        'BOSU Elbow to Knee Crunch' => [
            'category_id' => 2, 'equipment_type_id' => 6, 'default_rest_sec' => 45,
            'description' => 'Oblique crunch on a BOSU ball, bringing elbow to opposite knee.',
        ],
        'Prone Swimmers' => [
            'category_id' => 2, 'equipment_type_id' => 6, 'default_rest_sec' => 45,
            'description' => 'Prone back-extension holding a swimming-style arm motion to target the posterior chain.',
        ],
        'Russian Twist' => [
            'category_id' => 2, 'equipment_type_id' => 6, 'default_rest_sec' => 45,
            'description' => 'Seated rotational core exercise twisting the torso side to side.',
        ],
        'BOSU Weighted Sit-ups' => [
            'category_id' => 2, 'equipment_type_id' => 6, 'default_rest_sec' => 45,
            'description' => 'Sit-ups performed on a BOSU ball while holding a weight.',
        ],
        'BOSU Alternate Toe Touch' => [
            'category_id' => 2, 'equipment_type_id' => 6, 'default_rest_sec' => 45,
            'description' => 'Alternating toe-touch crunches performed on a BOSU ball.',
        ],
    ];

    /**
     * The program. Each day is a list of exercises in circuit order.
     * fields: name (must match catalogue), sets, reps, weight (kg), rest (sec)
     */
    private function program(): array
    {
        return [
            [
                'name' => 'Trening 1',
                'description' => '3 circuits × 3 rounds — legs, shoulders, arms & core.',
                'day_of_week' => 1,
                'exercises' => [
                    // Circuit A
                    ['name' => 'TRX Squat to Y Fly',              'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 60],
                    ['name' => 'Dumbbell Front Raises',          'sets' => 3, 'reps' => 24, 'weight' => 8,  'rest' => 60],
                    ['name' => 'Bench Dips',                     'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 60],
                    ['name' => 'TRX Rollout',                    'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 60],
                    // Circuit B
                    ['name' => 'Dumbbell Sumo Squat',           'sets' => 3, 'reps' => 15, 'weight' => 20, 'rest' => 60],
                    ['name' => 'Dumbbell Shoulder Press',       'sets' => 3, 'reps' => 12, 'weight' => 12, 'rest' => 60],
                    ['name' => 'Dumbbell Overhead Triceps Extension', 'sets' => 3, 'reps' => 15, 'weight' => 14, 'rest' => 60],
                    ['name' => 'BOSU Elbow to Knee Crunch',     'sets' => 3, 'reps' => 24, 'weight' => 0,  'rest' => 45],
                    // Circuit C
                    ['name' => 'Walking Lunges',                'sets' => 3, 'reps' => 20, 'weight' => 0,  'rest' => 60],
                    ['name' => 'Dumbbell Lateral Raises',       'sets' => 3, 'reps' => 12, 'weight' => 8,  'rest' => 60],
                    ['name' => 'Prone Swimmers',                'sets' => 3, 'reps' => 24, 'weight' => 0,  'rest' => 45],
                    ['name' => 'Russian Twist',                 'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 45],
                ],
            ],
            [
                'name' => 'Trening 2',
                'description' => '3 circuits × 3 rounds — back, chest, arms & core.',
                'day_of_week' => 3,
                'exercises' => [
                    // Circuit A
                    ['name' => 'TRX Row',                       'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 60],
                    ['name' => 'Incline Dumbbell Bench Press',  'sets' => 3, 'reps' => 15, 'weight' => 12, 'rest' => 60],
                    ['name' => 'Hammer Curl',                   'sets' => 3, 'reps' => 15, 'weight' => 10, 'rest' => 60], // reps not specified — defaulted to 15
                    ['name' => 'BOSU Weighted Sit-ups',         'sets' => 3, 'reps' => 15, 'weight' => 6,  'rest' => 45], // reps not specified — defaulted to 15
                    // Circuit B
                    ['name' => 'Dumbbell Row',                  'sets' => 3, 'reps' => 15, 'weight' => 14, 'rest' => 60], // "single arm row"
                    ['name' => 'Dumbbell Fly',                  'sets' => 3, 'reps' => 15, 'weight' => 12, 'rest' => 60], // "peck fly"
                    ['name' => 'Dumbbell Biceps Curl',          'sets' => 3, 'reps' => 24, 'weight' => 10, 'rest' => 60],
                    ['name' => 'TRX Pike',                      'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 45],
                    // Circuit C
                    ['name' => 'TRX Wide Row',                  'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 60],
                    ['name' => 'Push-ups',                      'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 60],
                    ['name' => 'BOSU Alternate Toe Touch',      'sets' => 3, 'reps' => 24, 'weight' => 0,  'rest' => 45],
                    ['name' => 'TRX Knee Tuck',                 'sets' => 3, 'reps' => 15, 'weight' => 0,  'rest' => 45],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $user = User::where('email', self::USER_EMAIL)->first();
        if (! $user) {
            $this->command->error('User '.self::USER_EMAIL.' not found. Aborting.');

            return;
        }

        // 1. Ensure the missing catalogue exercises exist.
        foreach (self::NEW_EXERCISES as $name => $attrs) {
            $exercise = Exercise::firstOrCreate(['name' => $name], $attrs);
            $this->command->info(($exercise->wasRecentlyCreated ? 'Created' : 'Exists').' exercise: '.$name);
        }

        // 2. Resolve every exercise name in the program to an id (fail loudly on typos).
        $names = collect($this->program())->flatMap(fn ($d) => collect($d['exercises'])->pluck('name'))->unique();
        $byName = Exercise::whereIn('name', $names)->get()->keyBy('name');
        $missing = $names->reject(fn ($n) => $byName->has($n));
        if ($missing->isNotEmpty()) {
            $this->command->error('Missing exercises in catalogue: '.$missing->implode(', ').'. Aborting.');

            return;
        }

        DB::transaction(function () use ($user, $byName) {
            // 3. Plan (matched by user + name so re-runs update in place).
            $plan = Plan::updateOrCreate(
                ['user_id' => $user->id, 'name' => self::PLAN_NAME],
                [
                    'partner_id' => null,
                    'type' => PlanType::Program,
                    'description' => '2-day circuit training program.',
                    'duration_weeks' => 8,
                    'is_active' => true,
                    'is_auto_generated' => false,
                ]
            );

            // 4. Rebuild templates from scratch for a clean, idempotent result.
            foreach ($plan->workoutTemplates as $existing) {
                $existing->workoutTemplateExercises()->delete();
                $existing->delete();
            }

            foreach ($this->program() as $dayIndex => $day) {
                $template = WorkoutTemplate::create([
                    'plan_id' => $plan->id,
                    'name' => $day['name'],
                    'description' => $day['description'],
                    'day_of_week' => $day['day_of_week'],
                    'week_number' => 1,
                    'order_index' => $dayIndex,
                ]);

                foreach ($day['exercises'] as $order => $ex) {
                    WorkoutTemplateExercise::create([
                        'workout_template_id' => $template->id,
                        'exercise_id' => $byName[$ex['name']]->id,
                        'order' => $order,
                        'target_sets' => $ex['sets'],
                        'min_target_reps' => $ex['reps'],
                        'max_target_reps' => $ex['reps'],
                        'target_weight' => $ex['weight'],
                        'rest_seconds' => $ex['rest'],
                    ]);
                }

                $this->command->info('Seeded '.$day['name'].' ('.count($day['exercises']).' exercises).');
            }

            $this->command->info('Plan #'.$plan->id.' ready for '.self::USER_EMAIL.'.');
        });
    }
}
