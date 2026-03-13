<?php

namespace Database\Seeders;

use App\Enums\PlanType;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Database\Seeder;

class RoutinePlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Seed routines for Premium Sport Center
        $premiumSportPartner = Partner::where('slug', 'premium-sport-center')->first();

        if ($premiumSportPartner) {
            $this->seedPremiumSportRoutines($premiumSportPartner);
        } else {
            $this->command->warn('Premium Sport Center partner not found. Skipping premium routines.');
        }
    }

    /**
     * Seed routines for Premium Sport Center partner.
     */
    private function seedPremiumSportRoutines(Partner $partner): void
    {
        $templates = [
            [
                'name' => 'Explosive Power & Squat Strength',
                'description' => 'Focus on Olympic lifting, front squats, and vertical pressing',
                'day_of_week' => 0,
                'exercises' => [
                    ['name' => 'Power Clean', 'weight' => 0], // 4×5
                    ['name' => 'Front Squat', 'weight' => 0], // 3×5
                    ['name' => 'Standing Landmine Press', 'weight' => 0], // 3×10 per side
                    ['name' => 'Medicine Ball Kneeling Slam', 'weight' => 0], // 3×8
                    ['name' => 'Close-Grip Pull-ups', 'weight' => 0], // 3×5 (fast concentric)
                    ['name' => 'Hyperextensions', 'weight' => 0], // 3×15
                    ['name' => 'Straight Leg Raises', 'weight' => 0], // 3×15
                ],
            ],
            [
                'name' => 'Plyometric & Hinge Power',
                'description' => 'Focus on explosive jumps, trap bar deadlifts, and rotational core',
                'day_of_week' => 1,
                'exercises' => [
                    ['name' => 'Box Jump', 'weight' => 0], // 4×5
                    ['name' => 'Kettlebell Swing', 'weight' => 0], // 3×10
                    ['name' => 'Trap Bar Deadlift', 'weight' => 0], // 4×5
                    ['name' => 'Medicine Ball Standing Side Slam', 'weight' => 0], // 3×10 per side
                    ['name' => 'Dumbbell Bench Press', 'weight' => 0], // 3×12
                    ['name' => 'Dips (Chest)', 'weight' => 0], // 3×10
                    ['name' => 'Half-Kneeling Landmine Rotation', 'weight' => 0], // 2×10
                ],
            ],
            [
                'name' => 'Posterior Chain & Overhead Power',
                'description' => 'Focus on back squats, push presses, and unilateral stability',
                'day_of_week' => 2,
                'exercises' => [
                    ['name' => 'Back Squat', 'weight' => 0], // 3×6
                    ['name' => 'Push Press', 'weight' => 0], // 3×5
                    ['name' => 'Single-Leg Landmine Romanian Deadlift', 'weight' => 0], // 3×10 per leg
                    ['name' => 'Medicine Ball Standing Side Slam', 'weight' => 0], // 2×8 per side
                    ['name' => 'Wide-Grip Pull-ups', 'weight' => 0], // 3×8
                    ['name' => 'Commando Plank', 'weight' => 0], // 3×10
                    ['name' => 'Hyperextensions', 'weight' => 0], // 3×15
                ],
            ],
            [
                'name' => 'Olympic Pull & Horizontal Push',
                'description' => 'Focus on squat cleans, Romanian deadlifts, and bench press strength',
                'day_of_week' => 3,
                'exercises' => [
                    ['name' => 'Squat Clean', 'weight' => 0], // 4×5
                    ['name' => 'Romanian Deadlift', 'weight' => 0], // 3×8
                    ['name' => 'TRX Hamstring Curl', 'weight' => 0], // 3×15
                    ['name' => 'Medicine Ball Slam', 'weight' => 0], // 3×8
                    ['name' => 'Barbell Bench Press', 'weight' => 0], // 4×5
                    ['name' => 'Landmine Row', 'weight' => 0], // 3×10 per side
                    ['name' => 'TRX Knee Tuck', 'weight' => 0], // 3×15
                ],
            ],
            [
                'name' => 'Full Body Speed & Conditioning',
                'description' => 'High-velocity movements combined with machine and bodyweight resistance',
                'day_of_week' => 4,
                'exercises' => [
                    ['name' => 'Power Clean', 'weight' => 0], // 3×5
                    ['name' => 'Box Jump', 'weight' => 0], // 4×5
                    ['name' => 'Leg Press', 'weight' => 0], // 3×10
                    ['name' => 'Underhand Close-Grip Lat Pulldown', 'weight' => 0], // 3×12
                    ['name' => 'TRX Atomic Push-Up', 'weight' => 0], // 3×10
                    ['name' => 'Arnold Press', 'weight' => 0], // 3×12
                    ['name' => 'Cross Body Mountain Climbers', 'weight' => 0], // 3×25
                ],
            ],
            [
                'name' => 'Integrated Strength & Stability',
                'description' => 'Kettlebell power, landmine thrusters, and unilateral lower body',
                'day_of_week' => 5,
                'exercises' => [
                    ['name' => 'Kettlebell Clean and Press', 'weight' => 0], // 3×6
                    ['name' => 'Weighted Squat Jump', 'weight' => 0], // 3×8
                    ['name' => 'Landmine Thruster', 'weight' => 0], // 3×10
                    ['name' => 'Single Leg Hip Thrust', 'weight' => 0], // 3×12 per leg
                    ['name' => 'TRX Hamstring Curl', 'weight' => 0], // 3×15
                    ['name' => 'Incline Barbell Bench Press', 'weight' => 0], // 3×10
                    ['name' => 'Renegade Row', 'weight' => 0], // 2×20
                    ['name' => 'Hyperextensions', 'weight' => 0], // 2×20
                ],
            ],
            [
                'name' => 'Unilateral Focus & Core Control',
                'description' => 'Single-leg explosive work, split squats, and TRX core finishers',
                'day_of_week' => 6,
                'exercises' => [
                    ['name' => 'Box Jump', 'weight' => 0], // 4×5 per leg
                    ['name' => 'Split Squat', 'weight' => 0], // 3×10 per leg
                    ['name' => 'Medicine Ball Rotational Throw', 'weight' => 0], // 3×10 per side
                    ['name' => 'TRX Y Fly', 'weight' => 0], // 3×10
                    ['name' => 'Half-Kneeling Alternating Landmine Press', 'weight' => 0], // 2×12 per arm
                    ['name' => 'Hyperextensions', 'weight' => 0], // 4×15
                    ['name' => 'TRX Pike', 'weight' => 0], // 4×15
                ],
            ],
            [
                'name' => 'Metabolic Strength & Rotation',
                'description' => 'KB cleans and Smith machine squats paired with rotational slams',
                'day_of_week' => 7,
                'exercises' => [
                    ['name' => 'Kettlebell Clean and Press', 'weight' => 0], // 3×8
                    ['name' => 'Smith Machine Squat', 'weight' => 0], // 3×10
                    ['name' => 'Medicine Ball Standing Side Slam', 'weight' => 0], // 3×13
                    ['name' => 'Bent-Over Row', 'weight' => 0], // 3×10
                    ['name' => 'Half-Kneeling Landmine Press', 'weight' => 0], // 3×10 per arm
                    ['name' => 'TRX Hamstring Curl', 'weight' => 0], // 3×20
                    ['name' => 'TRX Side Plank', 'weight' => 0], // 2×30 sec per side
                    ['name' => 'Cross Body Mountain Climbers', 'weight' => 0], // 2×25
                ],
            ],
            [
                'name' => 'Explosive Hinge & Upper Body Drive',
                'description' => 'Trap bar deadlifts and incline barbell bench focus',
                'day_of_week' => 8,
                'exercises' => [
                    ['name' => 'Box Jump', 'weight' => 0], // 4×5
                    ['name' => 'Trap Bar Deadlift', 'weight' => 0], // 3×8
                    ['name' => 'Medicine Ball Rotational Throw', 'weight' => 0], // 3×12 per side
                    ['name' => 'Incline Barbell Bench Press', 'weight' => 0], // 4×10
                    ['name' => 'Single-Arm Landmine Row', 'weight' => 0], // 3×10 per arm
                    ['name' => 'TRX Reverse Lunge with Knee Drive', 'weight' => 0], // 2×10 per leg
                    ['name' => 'TRX Pike', 'weight' => 0], // 2×15
                    ['name' => 'Standing Landmine Rotation', 'weight' => 0], // 2×20
                ],
            ],
            [
                'name' => 'Vertical Power & Lower Body Volume',
                'description' => 'Push press and front squat focus with accessory lateral movements',
                'day_of_week' => 9,
                'exercises' => [
                    ['name' => 'Push Press', 'weight' => 0], // 3×10
                    ['name' => 'Front Squat', 'weight' => 0], // 4×5
                    ['name' => 'Medicine Ball Slam', 'weight' => 0], // 4×5
                    ['name' => 'Wide-Grip Pull-ups', 'weight' => 0], // 3×5 (fast concentric)
                    ['name' => 'Dumbbell Bench Press', 'weight' => 0], // 3×12
                    ['name' => 'Kettlebell Lateral Lunge', 'weight' => 0], // 3×8 per side
                    ['name' => 'Straight Leg Raises', 'weight' => 0], // 2×15
                    ['name' => 'Half-Kneeling Landmine Rotation', 'weight' => 0], // 2×20
                ],
            ],
            [
                'name' => 'Technical Pull & Posterior Strength',
                'description' => 'Focus on snatches, sumo deadlifts, and pull-up volume',
                'day_of_week' => 10,
                'exercises' => [
                    ['name' => 'Power Snatch', 'weight' => 0], // 3×5
                    ['name' => 'Sumo Deadlift', 'weight' => 0], // 4×5
                    ['name' => 'Close-Grip Pull-ups', 'weight' => 0], // 3×10
                    ['name' => 'Reverse Pec Deck Fly', 'weight' => 0], // 3×15
                    ['name' => 'Dumbbell Cossack Lunge', 'weight' => 0], // 3×10
                    ['name' => 'TRX Body Saw', 'weight' => 0], // 2×10
                    ['name' => 'Army Plank', 'weight' => 0], // 2×1 min
                ],
            ],
        ];

        $plan = Plan::create([
            'partner_id' => $partner->id,
            'user_id' => null,
            'name' => 'Strength and Conditioning',
            'description' => 'A comprehensive strength and conditioning program focusing on power, strength, and athletic performance. Includes Olympic lifts, compound movements, and functional training.',
            'type' => PlanType::Routine,
            'is_active' => true,
        ]);

        $this->command->info("Created routine plan: {$plan->name}");

        foreach ($templates as $templateData) {
            $template = WorkoutTemplate::create([
                'plan_id' => $plan->id,
                'name' => $templateData['name'],
                'description' => $templateData['description'] ?? null,
                'day_of_week' => $templateData['day_of_week'],
            ]);

            foreach ($templateData['exercises'] as $index => $exerciseData) {
                $exercise = $this->findExerciseByName($exerciseData['name']);

                if (! $exercise) {
                    $this->command->warn("Exercise '{$exerciseData['name']}' not found. Skipping.");

                    continue;
                }

                WorkoutTemplateExercise::create([
                    'workout_template_id' => $template->id,
                    'exercise_id' => $exercise->id,
                    'order' => $index + 1,
                    'target_weight' => $exerciseData['weight'],
                ]);
            }

            $this->command->info("  Created workout: {$template->name}");
        }

        $this->command->info('Premium Sport Center routine plans seeded successfully!');
    }

    /**
     * Find exercise by name (exact or case-insensitive match).
     */
    private function findExerciseByName(string $name): ?Exercise
    {
        // Try exact match first
        $exercise = Exercise::where('name', $name)->first();
        if ($exercise) {
            return $exercise;
        }

        // Try case-insensitive match
        return Exercise::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }
}
