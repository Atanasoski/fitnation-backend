<?php

namespace Database\Seeders;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkoutSessionDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏋️ Creating workout sessions with set logs for fitness metrics...');

        $user = User::where('email', 'atanasoski992@gmail.com')->first();

        if (! $user) {
            $this->command->warn('⚠️ Demo user not found. Run UserSeeder first.');

            return;
        }

        $templates = WorkoutTemplate::whereHas('plan', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        if ($templates->isEmpty()) {
            $this->command->warn('⚠️ No workout templates found. Run WorkoutTemplateSeeder first.');

            return;
        }

        // Get exercises by category for realistic workout distribution
        $exercises = Exercise::with('category')
            ->get()
            ->groupBy('category.name');

        if ($exercises->isEmpty()) {
            $this->command->warn('⚠️ No exercises found. Run ExerciseSeeder first.');

            return;
        }

        // Create sessions over the last 60 days with set logs
        $this->createSessionsWithSetLogs($user, $templates, $exercises);

        $sessionCount = WorkoutSession::where('user_id', $user->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->where('performed_at', '>=', Carbon::now()->subDays(60))
            ->count();

        $setLogCount = SetLog::whereHas('workoutSession', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', WorkoutSessionStatus::Completed)
                ->where('performed_at', '>=', Carbon::now()->subDays(60));
        })->count();

        $this->command->info("✅ Created {$sessionCount} completed workout sessions with {$setLogCount} set logs.");
    }

    /**
     * Create workout sessions with set logs over the last 60 days.
     */
    private function createSessionsWithSetLogs(User $user, $templates, $exercises): void
    {
        $now = Carbon::now();
        $startDate = $now->copy()->subDays(60);

        // Create sessions roughly every 2-3 days
        $currentDate = $startDate->copy();

        while ($currentDate->lt($now)) {
            // 70% chance of having a workout on any given day
            if (fake()->boolean(70)) {
                $performedAt = $currentDate->copy()->setTime(
                    fake()->numberBetween(6, 20),
                    fake()->randomElement([0, 15, 30, 45])
                );

                $duration = fake()->numberBetween(30, 90);
                $completedAt = $performedAt->copy()->addMinutes($duration);

                // Only create completed sessions for fitness metrics
                $session = WorkoutSession::create([
                    'user_id' => $user->id,
                    'workout_template_id' => $templates->random()->id,
                    'performed_at' => $performedAt,
                    'completed_at' => $completedAt,
                    'notes' => fake()->boolean(30) ? fake()->sentence() : null,
                ]);

                // Create set logs for this session
                $this->createSetLogsForSession($session, $exercises, $performedAt);
            }

            // Move to next day (with some randomness)
            $currentDate->addDays(fake()->numberBetween(1, 3));
        }
    }

    /**
     * Create realistic set logs for a workout session.
     */
    private function createSetLogsForSession(WorkoutSession $session, $exercises, Carbon $performedAt): void
    {
        // Determine workout focus based on day of week (for variety)
        $dayOfWeek = $performedAt->dayOfWeek;
        $focusCategories = $this->getFocusCategoriesForDay($dayOfWeek);

        // Select 4-8 exercises for this session
        $selectedExercises = collect();
        foreach ($focusCategories as $categoryName) {
            if (isset($exercises[$categoryName]) && $exercises[$categoryName]->isNotEmpty()) {
                $selectedExercises = $selectedExercises->merge(
                    $exercises[$categoryName]->random(min(2, $exercises[$categoryName]->count()))
                );
            }
        }

        // Ensure we have at least 4 exercises
        while ($selectedExercises->count() < 4) {
            $randomCategory = $exercises->keys()->random();
            if (isset($exercises[$randomCategory]) && $exercises[$randomCategory]->isNotEmpty()) {
                $selectedExercises->push($exercises[$randomCategory]->random());
            }
        }

        $selectedExercises = $selectedExercises->unique('id')->take(8)->values();

        // Create set logs for each exercise
        foreach ($selectedExercises as $order => $exercise) {
            $sets = fake()->numberBetween(3, 5);
            $baseWeight = $this->getBaseWeightForExercise($exercise->name);
            $baseReps = fake()->numberBetween(8, 12);

            // Every set belongs to a session-exercise row; seeding the logs
            // without one leaves them unattached and invisible in the API.
            $sessionExercise = $session->workoutSessionExercises()->create([
                'exercise_id' => $exercise->id,
                'order' => $order,
                'target_sets' => $sets,
                'min_target_reps' => 8,
                'max_target_reps' => 12,
                'target_weight' => $baseWeight,
                'rest_seconds' => $exercise->default_rest_sec ?? 60,
            ]);

            for ($setNumber = 1; $setNumber <= $sets; $setNumber++) {
                // Progressive overload: weight increases slightly, reps may decrease
                $weight = $baseWeight + (($setNumber - 1) * fake()->randomFloat(2, 0, 2.5));
                $reps = max(6, $baseReps - fake()->numberBetween(0, 2));

                // Add some realistic variation
                $weight = max(5, $weight + fake()->randomFloat(2, -2, 2));
                $reps = max(1, $reps + fake()->numberBetween(-1, 1));

                SetLog::create([
                    'workout_session_id' => $session->id,
                    'workout_session_exercise_id' => $sessionExercise->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $setNumber,
                    'weight' => round($weight, 2),
                    'reps' => $reps,
                    'rest_seconds' => $exercise->default_rest_sec ?? 60,
                ]);
            }
        }
    }

    /**
     * Get focus categories based on day of week for workout variety.
     */
    private function getFocusCategoriesForDay(int $dayOfWeek): array
    {
        // Create a varied workout schedule
        $schedule = [
            0 => ['Chest', 'Shoulders', 'Arms'], // Sunday
            1 => ['Legs', 'Core'], // Monday
            2 => ['Back', 'Arms'], // Tuesday
            3 => ['Chest', 'Shoulders'], // Wednesday
            4 => ['Legs', 'Core'], // Thursday
            5 => ['Back', 'Arms'], // Friday
            6 => ['Chest', 'Shoulders', 'Core'], // Saturday
        ];

        return $schedule[$dayOfWeek] ?? ['Chest', 'Back', 'Legs'];
    }

    /**
     * Get base weight for an exercise (realistic starting weights).
     */
    private function getBaseWeightForExercise(string $exerciseName): float
    {
        $name = strtolower($exerciseName);

        // Compound movements (heavier)
        if (str_contains($name, 'squat') || str_contains($name, 'deadlift')) {
            return fake()->randomFloat(2, 80, 150);
        }
        if (str_contains($name, 'bench press') || str_contains($name, 'row')) {
            return fake()->randomFloat(2, 60, 120);
        }
        if (str_contains($name, 'press') && str_contains($name, 'overhead')) {
            return fake()->randomFloat(2, 40, 80);
        }
        if (str_contains($name, 'leg press')) {
            return fake()->randomFloat(2, 100, 200);
        }

        // Isolation movements (lighter)
        if (str_contains($name, 'curl') || str_contains($name, 'extension')) {
            return fake()->randomFloat(2, 10, 30);
        }
        if (str_contains($name, 'raise') || str_contains($name, 'fly')) {
            return fake()->randomFloat(2, 8, 25);
        }
        if (str_contains($name, 'pulldown') || str_contains($name, 'pull-up')) {
            return fake()->randomFloat(2, 50, 100);
        }

        // Core exercises (bodyweight or light)
        if (str_contains($name, 'plank') || str_contains($name, 'crunch')) {
            return 0; // Bodyweight
        }

        // Default for other exercises
        return fake()->randomFloat(2, 20, 60);
    }
}
