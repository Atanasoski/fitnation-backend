<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Angle;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\MuscleGroup;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use Illuminate\Database\Seeder;

/**
 * Catalog enrichment (issue #108): the exercises from the team's spreadsheet
 * that the production catalog lacked, imported with explicit classification.
 *
 * Band coverage above all — BAND was a selectable equipment type with zero
 * exercises, so a band-only user got "no exercises available".
 *
 * Every entry is classified by hand (movement pattern, angle where meaningful,
 * muscle groups); the target region is derived from the movement pattern via
 * the region-ownership map, which puts arm isolations under ARMS, never
 * UPPER_PUSH/UPPER_PULL. Names that already exist — in any state, including
 * archived — are left untouched, so re-running the seeder is a no-op and
 * production rows are never modified or resurrected.
 */
class CatalogEnrichmentSeeder extends Seeder
{
    /**
     * The spreadsheet exercises missing from the catalog.
     * Format: name, equipment (code), pattern (code), angle (code|null),
     * primary / secondary muscle group names, rest (sec), description.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exercises(): array
    {
        return [
            [
                'name' => 'Band Biceps Curl',
                'equipment' => 'BAND',
                'pattern' => 'ELBOW_FLEXION',
                'angle' => null,
                'primary' => ['Biceps'],
                'secondary' => ['Forearms'],
                'rest' => 60,
                'description' => 'Stand on the band with feet shoulder-width, grip the handles palms up, curl toward your shoulders keeping elbows pinned to your sides, lower slowly against the band\'s pull.',
            ],
            [
                'name' => 'Band Chest Press',
                'equipment' => 'BAND',
                'pattern' => 'PRESS',
                'angle' => 'FLAT',
                'primary' => ['Chest'],
                'secondary' => ['Triceps', 'Front Delts'],
                'rest' => 60,
                'description' => 'Anchor the band behind you at chest height, press the handles forward until arms extend, keep shoulder blades back, return under control against the band\'s resistance.',
            ],
            [
                'name' => 'Band Chest Fly',
                'equipment' => 'BAND',
                'pattern' => 'FLY',
                'angle' => 'FLAT',
                'primary' => ['Chest'],
                'secondary' => ['Front Delts'],
                'rest' => 60,
                'description' => 'Anchor the band behind you at chest height, arms out wide with a soft elbow bend, bring the handles together in front of your chest in an arc, open back up slowly.',
            ],
            [
                'name' => 'Band Overhead Press',
                'equipment' => 'BAND',
                'pattern' => 'PRESS',
                'angle' => 'VERTICAL',
                'primary' => ['Front Delts', 'Side Delts'],
                'secondary' => ['Triceps'],
                'rest' => 60,
                'description' => 'Stand on the band, bring the handles to shoulder height, press straight overhead until arms lock out, lower back to the shoulders with control.',
            ],
            [
                'name' => 'Band Lateral Raise',
                'equipment' => 'BAND',
                'pattern' => 'SHOULDER_RAISE',
                'angle' => 'HORIZONTAL',
                'primary' => ['Side Delts'],
                'secondary' => ['Traps'],
                'rest' => 60,
                'description' => 'Stand on the band with handles at your sides, raise both arms out to shoulder height with a slight elbow bend, pause, lower slowly against the band\'s pull.',
            ],
            [
                'name' => 'Band Row',
                'equipment' => 'BAND',
                'pattern' => 'ROW',
                'angle' => 'HORIZONTAL',
                'primary' => ['Lats', 'Upper Back'],
                'secondary' => ['Biceps'],
                'rest' => 60,
                'description' => 'Anchor the band at torso height, step back until it is taut, pull the handles to your ribs squeezing shoulder blades together, return slowly to full stretch.',
            ],
            [
                'name' => 'Seated Band Row',
                'equipment' => 'BAND',
                'pattern' => 'ROW',
                'angle' => 'HORIZONTAL',
                'primary' => ['Lats', 'Upper Back'],
                'secondary' => ['Biceps'],
                'rest' => 60,
                'description' => 'Sit with legs extended, loop the band around your feet, pull the handles to your torso keeping your back tall and elbows close, release slowly to a full stretch.',
            ],
            [
                'name' => 'Band Face Pull',
                'equipment' => 'BAND',
                'pattern' => 'FACE_PULL',
                'angle' => 'HORIZONTAL',
                'primary' => ['Rear Delts', 'Upper Back'],
                'secondary' => ['Traps'],
                'rest' => 60,
                'description' => 'Anchor the band at face height, pull the band toward your face with elbows high and hands apart, squeeze the rear delts, return under control.',
            ],
            [
                'name' => 'Band Pull-Apart',
                'equipment' => 'BAND',
                'pattern' => 'REAR_DELT_FLY',
                'angle' => 'HORIZONTAL',
                'primary' => ['Rear Delts'],
                'secondary' => ['Upper Back', 'Traps'],
                'rest' => 45,
                'description' => 'Hold the band at shoulder height with straight arms, pull the ends apart until the band touches your chest, squeeze shoulder blades together, return slowly.',
            ],
            [
                'name' => 'Band Triceps Pushdown',
                'equipment' => 'BAND',
                'pattern' => 'ELBOW_EXTENSION',
                'angle' => null,
                'primary' => ['Triceps'],
                'secondary' => ['Forearms'],
                'rest' => 60,
                'description' => 'Anchor the band overhead, grip with elbows pinned to your sides, extend your arms down until they lock out, resist the band on the way back up.',
            ],
            [
                'name' => 'Band Squat',
                'equipment' => 'BAND',
                'pattern' => 'SQUAT',
                'angle' => null,
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Hamstrings'],
                'rest' => 60,
                'description' => 'Stand on the band with handles at your shoulders, squat down keeping your chest up and knees tracking over toes, drive up against the band\'s resistance.',
            ],
            [
                'name' => 'Band Lateral Monster Walk',
                'equipment' => 'BAND',
                'pattern' => 'HIP_ABDUCTION',
                'angle' => null,
                'primary' => ['Glutes'],
                'secondary' => ['Quadriceps'],
                'rest' => 45,
                'description' => 'Place the band around your legs above the knees, sink into a quarter squat, step sideways keeping tension on the band, repeat in both directions.',
            ],

            // =========================
            // KETTLEBELL
            // =========================
            [
                'name' => 'Kettlebell Bench Press',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'PRESS',
                'angle' => 'FLAT',
                'primary' => ['Chest'],
                'secondary' => ['Triceps', 'Front Delts'],
                'rest' => 90,
                'description' => 'Lie on a flat bench with a kettlebell in each hand resting against your forearms, press up to lockout, lower with control keeping wrists neutral.',
            ],
            [
                'name' => 'Kettlebell Biceps Curl',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'ELBOW_FLEXION',
                'angle' => null,
                'primary' => ['Biceps'],
                'secondary' => ['Forearms'],
                'rest' => 60,
                'description' => 'Hold a kettlebell in each hand at your sides, curl toward your shoulders keeping elbows pinned, control the offset weight on the way down.',
            ],
            [
                'name' => 'Kettlebell Overhead Press',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'PRESS',
                'angle' => 'VERTICAL',
                'primary' => ['Front Delts', 'Side Delts'],
                'secondary' => ['Triceps'],
                'rest' => 90,
                'description' => 'Clean the kettlebells to the rack position at your shoulders, press straight overhead until arms lock out, lower back to the rack under control.',
            ],
            [
                'name' => 'Kettlebell Overhead Triceps Extension',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'ELBOW_EXTENSION',
                'angle' => 'VERTICAL',
                'primary' => ['Triceps'],
                'secondary' => [],
                'rest' => 60,
                'description' => 'Hold a kettlebell by the horns overhead, lower it behind your head by bending the elbows, keep upper arms vertical, extend back to lockout.',
            ],
            [
                'name' => 'Kettlebell Romanian Deadlift',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'HINGE',
                'angle' => null,
                'primary' => ['Hamstrings', 'Glutes'],
                'secondary' => ['Lower Back'],
                'rest' => 90,
                'description' => 'Hold a kettlebell in each hand in front of your thighs, hinge at the hips with a flat back until you feel a hamstring stretch, drive hips forward to stand.',
            ],
            [
                'name' => 'Single-Arm Kettlebell Press',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'PRESS',
                'angle' => 'VERTICAL',
                'primary' => ['Front Delts', 'Side Delts'],
                'secondary' => ['Triceps', 'Obliques'],
                'rest' => 75,
                'description' => 'Rack one kettlebell at your shoulder, brace your core, press overhead to lockout without leaning, lower under control; repeat on the other side.',
            ],
            [
                'name' => 'Single-Arm Kettlebell Row',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'ROW',
                'angle' => 'HORIZONTAL',
                'primary' => ['Lats', 'Upper Back'],
                'secondary' => ['Biceps'],
                'rest' => 75,
                'description' => 'Hinge forward with one hand braced on a bench, row the kettlebell to your hip keeping your elbow close, lower to a full stretch; repeat on the other side.',
            ],
            [
                'name' => 'Sumo Squat (Kettlebell)',
                'equipment' => 'KETTLEBELL',
                'pattern' => 'SQUAT',
                'angle' => 'VERTICAL',
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Hamstrings'],
                'rest' => 75,
                'description' => 'Stand wide with toes out holding a kettlebell between your legs, squat down keeping your chest tall, drive through your heels to stand.',
            ],

            // =========================
            // DUMBBELL
            // =========================
            [
                'name' => 'Dumbbell Back Squat',
                'equipment' => 'DUMBBELL',
                'pattern' => 'SQUAT',
                'angle' => null,
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Hamstrings', 'Lower Back'],
                'rest' => 90,
                'description' => 'Hold a dumbbell in each hand at your sides or at your shoulders, squat down keeping your chest up and knees tracking over toes, drive up through your heels.',
            ],
            [
                'name' => 'Dumbbell Bulgarian Split Squat',
                'equipment' => 'DUMBBELL',
                'pattern' => 'LUNGE_SPLIT_SQUAT',
                'angle' => null,
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Hamstrings'],
                'rest' => 90,
                'description' => 'Hold dumbbells at your sides with your rear foot elevated on a bench, lower until your front thigh is parallel, drive up through the front heel; repeat per side.',
            ],
            [
                'name' => 'Dumbbell Front Squat',
                'equipment' => 'DUMBBELL',
                'pattern' => 'SQUAT',
                'angle' => 'VERTICAL',
                'primary' => ['Quadriceps'],
                'secondary' => ['Glutes', 'Abs'],
                'rest' => 90,
                'description' => 'Hold dumbbells at your shoulders with elbows up, squat down keeping your torso upright, drive up without letting the elbows drop.',
            ],
            [
                'name' => 'Dumbbell Hip Thrust',
                'equipment' => 'DUMBBELL',
                'pattern' => 'HIP_THRUST_BRIDGE',
                'angle' => null,
                'primary' => ['Glutes'],
                'secondary' => ['Hamstrings'],
                'rest' => 90,
                'description' => 'Rest your upper back on a bench with a dumbbell across your hips, drive your hips up until your torso is level, squeeze the glutes, lower with control.',
            ],
            [
                'name' => 'Dumbbell Romanian Deadlift',
                'equipment' => 'DUMBBELL',
                'pattern' => 'HINGE',
                'angle' => null,
                'primary' => ['Hamstrings', 'Glutes'],
                'secondary' => ['Lower Back'],
                'rest' => 90,
                'description' => 'Hold dumbbells in front of your thighs, hinge at the hips with a flat back sliding the dumbbells down your legs, drive hips forward to stand tall.',
            ],
            [
                'name' => 'Single-Arm Dumbbell Shoulder Press',
                'equipment' => 'DUMBBELL',
                'pattern' => 'PRESS',
                'angle' => 'VERTICAL',
                'primary' => ['Front Delts', 'Side Delts'],
                'secondary' => ['Triceps', 'Obliques'],
                'rest' => 75,
                'description' => 'Hold one dumbbell at shoulder height, brace your core, press overhead to lockout without leaning sideways, lower under control; repeat on the other side.',
            ],
            [
                'name' => 'Dumbbell Triceps Kickback',
                'equipment' => 'DUMBBELL',
                'pattern' => 'ELBOW_EXTENSION',
                'angle' => 'HORIZONTAL',
                'primary' => ['Triceps'],
                'secondary' => [],
                'rest' => 60,
                'description' => 'Hinge forward with a flat back, upper arms parallel to the floor, extend the dumbbells back until arms lock out, squeeze, return without dropping the elbows.',
            ],
            [
                'name' => 'Overhead Triceps Extension (Dumbbell)',
                'equipment' => 'DUMBBELL',
                'pattern' => 'ELBOW_EXTENSION',
                'angle' => 'VERTICAL',
                'primary' => ['Triceps'],
                'secondary' => [],
                'rest' => 60,
                'description' => 'Hold one dumbbell overhead with both hands, lower it behind your head by bending the elbows, keep upper arms vertical, extend back to lockout.',
            ],

            // =========================
            // CABLE
            // =========================
            [
                'name' => 'Behind-the-Back Cable Curl',
                'equipment' => 'CABLE',
                'pattern' => 'ELBOW_FLEXION',
                'angle' => null,
                'primary' => ['Biceps'],
                'secondary' => ['Forearms'],
                'rest' => 60,
                'description' => 'Set the pulley low, stand a step ahead with the handle behind you, curl forward and up keeping the elbow behind your torso for a full stretch; repeat per side.',
            ],
            [
                'name' => 'Cable Preacher Curl',
                'equipment' => 'CABLE',
                'pattern' => 'ELBOW_FLEXION',
                'angle' => null,
                'primary' => ['Biceps'],
                'secondary' => ['Forearms'],
                'rest' => 60,
                'description' => 'Set a preacher bench in front of a low pulley, rest your upper arms on the pad, curl the bar toward your shoulders, lower slowly to a full stretch.',
            ],
            [
                'name' => 'Cable Pull-Through',
                'equipment' => 'CABLE',
                'pattern' => 'HINGE',
                'angle' => null,
                'primary' => ['Glutes', 'Hamstrings'],
                'secondary' => ['Lower Back'],
                'rest' => 75,
                'description' => 'Face away from a low pulley with the rope between your legs, hinge at the hips letting the rope pull back, drive hips forward and squeeze the glutes to stand.',
            ],
            [
                'name' => 'Cable Romanian Deadlift',
                'equipment' => 'CABLE',
                'pattern' => 'HINGE',
                'angle' => null,
                'primary' => ['Hamstrings', 'Glutes'],
                'secondary' => ['Lower Back'],
                'rest' => 90,
                'description' => 'Face a low pulley holding the bar, step back, hinge at the hips with a flat back until you feel a hamstring stretch, drive hips forward to stand tall.',
            ],
            [
                'name' => 'Cable Squat',
                'equipment' => 'CABLE',
                'pattern' => 'SQUAT',
                'angle' => null,
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Hamstrings'],
                'rest' => 75,
                'description' => 'Hold a low-pulley handle or rope at your chest, lean back slightly against the cable, squat down keeping your chest up, drive through your heels to stand.',
            ],

            // =========================
            // BARBELL
            // =========================
            [
                'name' => 'Barbell Shrugs',
                'equipment' => 'BARBELL',
                'pattern' => 'ROW',
                'angle' => 'VERTICAL',
                'primary' => ['Traps'],
                'secondary' => ['Forearms'],
                'rest' => 60,
                'description' => 'Hold the bar in front of your thighs with straight arms, shrug your shoulders straight up toward your ears, pause at the top, lower slowly.',
            ],
            [
                'name' => 'Good Mornings',
                'equipment' => 'BARBELL',
                'pattern' => 'HINGE',
                'angle' => null,
                'primary' => ['Hamstrings', 'Lower Back'],
                'secondary' => ['Glutes'],
                'rest' => 90,
                'description' => 'Rest the bar on your upper back, soften the knees, hinge at the hips with a flat back until your torso nears parallel, drive hips forward to stand.',
            ],
            [
                'name' => 'Zercher Squat',
                'equipment' => 'BARBELL',
                'pattern' => 'SQUAT',
                'angle' => null,
                'primary' => ['Quadriceps', 'Glutes'],
                'secondary' => ['Abs', 'Upper Back'],
                'rest' => 120,
                'description' => 'Cradle the bar in the crooks of your elbows at chest height, squat down keeping your torso upright, drive up through your heels without letting the bar drift.',
            ],

            // =========================
            // BODYWEIGHT
            // =========================
            [
                'name' => '45-Degree Back Extension',
                'equipment' => 'BODYWEIGHT',
                'pattern' => 'BACK_EXTENSION',
                'angle' => null,
                'primary' => ['Lower Back', 'Glutes'],
                'secondary' => ['Hamstrings'],
                'rest' => 60,
                'description' => 'Set up on a 45-degree hyperextension bench with hips on the pad, lower your torso with a flat back, raise until your body forms a straight line, squeeze the glutes.',
            ],
        ];
    }

    public function run(): void
    {
        // Lookup tables are idempotent; calling them keeps this seeder
        // standalone for one-off production runs.
        $this->call([
            CategorySeeder::class,
            MuscleGroupSeeder::class,
            MovementPatternSeeder::class,
            TargetRegionSeeder::class,
            EquipmentTypeSeeder::class,
            AngleSeeder::class,
            TrainingStyleSeeder::class,
        ]);

        $categories = Category::pluck('id', 'slug');
        $muscleGroups = MuscleGroup::pluck('id', 'name');
        $patterns = MovementPattern::pluck('id', 'code');
        $regions = TargetRegion::pluck('id', 'code');
        $equipmentTypes = EquipmentType::pluck('id', 'code');
        $angles = Angle::pluck('id', 'code');
        $trainingStyles = TrainingStyle::pluck('id', 'code');

        $created = 0;
        $skipped = 0;

        foreach ($this->exercises() as $data) {
            // Leave existing rows untouched — in any state, including archived.
            if (Exercise::query()->where('name', $data['name'])->exists()) {
                $skipped++;

                continue;
            }

            $exercise = Exercise::create([
                'name' => $data['name'],
                'category_id' => $categories['strength-training'],
                'movement_pattern_id' => $patterns[$data['pattern']],
                'target_region_id' => $regions[ExerciseClassificationSeeder::MOVEMENT_TO_TARGET[$data['pattern']]],
                'equipment_type_id' => $equipmentTypes[$data['equipment']],
                'angle_id' => $data['angle'] !== null ? $angles[$data['angle']] : null,
                'default_rest_sec' => $data['rest'],
                'description' => $data['description'],
            ]);

            $muscleAttachments = [];
            foreach ($data['primary'] as $muscleName) {
                $muscleAttachments[$muscleGroups[$muscleName]] = ['is_primary' => true];
            }
            foreach ($data['secondary'] as $muscleName) {
                $muscleAttachments[$muscleGroups[$muscleName]] = ['is_primary' => false];
            }
            $exercise->muscleGroups()->sync($muscleAttachments);

            // BODYBUILDING is the catalog default; functional implements
            // (bands, kettlebells) also carry FUNCTIONAL, matching the
            // classification seeder's inference.
            $styleIds = [$trainingStyles['BODYBUILDING']];
            if (in_array($data['equipment'], EquipmentType::FUNCTIONAL_CODES, true)) {
                $styleIds[] = $trainingStyles['FUNCTIONAL'];
            }
            $exercise->trainingStyles()->sync($styleIds);

            $created++;
        }

        $this->command->info("Catalog enrichment complete: {$created} created, {$skipped} already present.");
    }
}
