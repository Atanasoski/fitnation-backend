<?php

namespace App\Services;

use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FitnessMetricsService
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user->load('profile');
    }

    /**
     * Get all fitness metrics for the user.
     */
    public function getMetrics(): array
    {
        return [
            'strength_score' => $this->getStrengthScore(),
            'strength_balance' => $this->getStrengthBalance(),
            'weekly_progress' => $this->getWeeklyProgress(),
        ];
    }

    /**
     * Calculate strength score based on 1RM estimates and relative strength.
     */
    private function getStrengthScore(): array
    {
        $profile = $this->user->profile;
        if (! $profile || ! $profile->weight) {
            return $this->getDefaultStrengthScore();
        }

        $recent30Days = Carbon::now()->subDays(30);
        $previous30Days = Carbon::now()->subDays(60);

        // Get recent and previous period data
        $recentSets = $this->getUserSetLogs()->where('workout_sessions.performed_at', '>=', $recent30Days);
        $previousSets = $this->getUserSetLogs()
            ->where('workout_sessions.performed_at', '>=', $previous30Days)
            ->where('workout_sessions.performed_at', '<', $recent30Days);

        // Calculate weighted strength scores
        $currentScore = $this->calculateRelativeStrengthScore($recentSets, $profile->weight);
        $previousScore = $this->calculateRelativeStrengthScore($previousSets, $profile->weight);

        $recentGain = $currentScore - $previousScore;
        $level = $this->determineStrengthLevel($currentScore, $profile);

        // Calculate percentile and muscle group scores
        $percentile = $this->calculateStrengthPercentile($currentScore, $profile);
        $muscleGroups = $this->calculateMuscleGroupStrengthScores($recentSets, $profile->weight);

        $result = [
            'current' => (int) round($currentScore),
            'level' => $level,
            'recent_gain' => (int) round($recentGain),
            'gain_period' => 'last_30_days',
        ];

        if ($percentile !== null) {
            $result['percentile'] = $percentile;
        }

        if (! empty($muscleGroups)) {
            $result['muscle_groups'] = $muscleGroups;
        }

        return $result;
    }

    /**
     * Calculate strength balance across muscle groups.
     */
    private function getStrengthBalance(): array
    {
        $recent30Days = Carbon::now()->subDays(30);
        $previous30Days = Carbon::now()->subDays(60);

        // Get volume data for muscle groups
        $recentVolumes = $this->getMuscleGroupVolumes($recent30Days);
        $previousVolumes = $this->getMuscleGroupVolumes($previous30Days, $recent30Days);

        // Calculate balance
        $totalVolume = array_sum($recentVolumes);
        $muscleGroups = [];

        if ($totalVolume > 0) {
            foreach ($recentVolumes as $muscleGroup => $volume) {
                $muscleGroups[strtolower($muscleGroup)] = (int) round(($volume / $totalVolume) * 100);
            }
        }

        // Ensure we have all required muscle groups (granular)
        foreach ($this->getRequiredMuscleGroups() as $group) {
            if (! isset($muscleGroups[$group])) {
                $muscleGroups[$group] = 0;
            }
        }

        // Calculate balance percentage and level
        $balancePercentage = $this->calculateBalancePercentage($muscleGroups);
        $balanceLevel = $this->determineBalanceLevel($balancePercentage);

        // Calculate recent change
        $previousBalance = $this->calculateBalancePercentage(
            $this->getMuscleGroupPercentages($previousVolumes)
        );
        $recentChange = $balancePercentage - $previousBalance;

        // Calculate percentile
        $percentile = $this->calculateBalancePercentile($balancePercentage, $this->user->profile);

        $result = [
            'percentage' => (int) round($balancePercentage),
            'level' => $balanceLevel,
            'recent_change' => (int) round($recentChange),
            'muscle_groups' => $muscleGroups,
        ];

        if ($percentile !== null) {
            $result['percentile'] = $percentile;
        }

        return $result;
    }

    /**
     * Calculate weekly progress comparison.
     */
    private function getWeeklyProgress(): array
    {
        $currentWeekStart = Carbon::now()->startOfWeek(); // Monday
        $currentWeekEnd = Carbon::now()->endOfWeek(); // Sunday
        $previousWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $previousWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        // Get current week sessions with set logs
        $currentWeekSessions = WorkoutSession::where('user_id', $this->user->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereBetween('performed_at', [$currentWeekStart, $currentWeekEnd])
            ->with('setLogs')
            ->get();

        // Get previous week sessions with set logs
        $previousWeekSessions = WorkoutSession::where('user_id', $this->user->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereBetween('performed_at', [$previousWeekStart, $previousWeekEnd])
            ->with('setLogs')
            ->get();

        $currentWeekWorkouts = $currentWeekSessions->count();
        $previousWeekWorkouts = $previousWeekSessions->count();

        // Conversion factor: 1 kg = 2.20462 lbs
        $kgToLbs = 2.20462;

        // Calculate volume (weight × reps) for current week
        // Weight is stored in KG, convert to lbs for API response
        $currentWeekVolume = 0;
        $currentWeekTimeMinutes = 0;
        foreach ($currentWeekSessions as $session) {
            foreach ($session->setLogs as $setLog) {
                // Volume in KG, convert to lbs
                $currentWeekVolume += ($setLog->weight * $setLog->reps) * $kgToLbs;
            }
            // Calculate duration in minutes
            if ($session->performed_at && $session->completed_at) {
                $currentWeekTimeMinutes += $session->performed_at->diffInMinutes($session->completed_at);
            }
        }

        // Calculate volume for previous week
        $previousWeekVolume = 0;
        foreach ($previousWeekSessions as $session) {
            foreach ($session->setLogs as $setLog) {
                // Volume in KG, convert to lbs
                $previousWeekVolume += ($setLog->weight * $setLog->reps) * $kgToLbs;
            }
        }

        // Calculate volume difference
        $volumeDifference = $currentWeekVolume - $previousWeekVolume;
        $volumeDifferencePercent = 0;
        if ($previousWeekVolume > 0) {
            $volumeDifferencePercent = ($volumeDifference / $previousWeekVolume) * 100;
        } elseif ($currentWeekVolume > 0) {
            $volumeDifferencePercent = 100; // 100% increase from 0
        }

        // Calculate percentage change (workout count)
        $percentage = 0;
        $trend = 'same';

        if ($previousWeekWorkouts > 0) {
            $percentage = (($currentWeekWorkouts - $previousWeekWorkouts) / $previousWeekWorkouts) * 100;
        } elseif ($currentWeekWorkouts > 0) {
            $percentage = 100; // 100% increase from 0
        }

        if ($percentage > 0) {
            $trend = 'up';
        } elseif ($percentage < 0) {
            $trend = 'down';
        }

        // Create daily breakdown for current week
        $dailyBreakdown = $this->getDailyBreakdown($currentWeekStart, $currentWeekEnd, $currentWeekSessions);

        // Get historical weekly progress
        $historicalWeeks = $this->getHistoricalWeeklyProgress(8);

        $result = [
            'percentage' => (int) round(abs($percentage)),
            'trend' => $trend,
            'current_week_workouts' => $currentWeekWorkouts,
            'previous_week_workouts' => $previousWeekWorkouts,
        ];

        // Add optional volume fields
        if ($currentWeekVolume > 0 || $previousWeekVolume > 0) {
            $result['current_week_volume'] = (int) round($currentWeekVolume);
            $result['previous_week_volume'] = (int) round($previousWeekVolume);
            $result['volume_difference'] = (int) round($volumeDifference);
            $result['volume_difference_percent'] = (int) round($volumeDifferencePercent);
        }

        // Add optional time field
        if ($currentWeekTimeMinutes > 0) {
            $result['current_week_time_minutes'] = (int) round($currentWeekTimeMinutes);
        }

        // Add daily breakdown
        if (! empty($dailyBreakdown)) {
            $result['daily_breakdown'] = $dailyBreakdown;
        }

        // Add historical weeks
        if (! empty($historicalWeeks)) {
            $result['historical_weeks'] = $historicalWeeks;
        }

        return $result;
    }

    /**
     * Get user's set logs with related data.
     */
    private function getUserSetLogs()
    {
        return DB::table('workout_session_set_logs')
            ->join('workout_sessions', 'workout_session_set_logs.workout_session_id', '=', 'workout_sessions.id')
            ->join('workout_exercises', 'workout_session_set_logs.exercise_id', '=', 'workout_exercises.id')
            ->join('exercise_muscle_group', 'workout_exercises.id', '=', 'exercise_muscle_group.exercise_id')
            ->join('muscle_groups', 'exercise_muscle_group.muscle_group_id', '=', 'muscle_groups.id')
            ->where('workout_sessions.user_id', $this->user->id)
            ->whereNotNull('workout_sessions.completed_at')
            ->whereNotNull('workout_session_set_logs.weight')
            ->where('workout_session_set_logs.weight', '>', 0)
            ->where('workout_session_set_logs.reps', '>', 0)
            ->where('exercise_muscle_group.is_primary', true)
            ->select([
                'workout_session_set_logs.*',
                'workout_sessions.performed_at',
                'workout_exercises.name as exercise_name',
                'muscle_groups.name as muscle_group_name',
                'muscle_groups.body_region',
            ]);
    }

    /**
     * Calculate relative strength score from set data.
     */
    private function calculateRelativeStrengthScore($sets, float $bodyWeight): float
    {
        $exerciseMaxes = [];

        foreach ($sets->get() as $set) {
            // Calculate 1RM using Epley formula: 1RM = weight × (1 + reps/30)
            $oneRM = $set->weight * (1 + ($set->reps / 30));

            // Keep track of best 1RM for each exercise
            $exerciseKey = $set->exercise_name;
            if (! isset($exerciseMaxes[$exerciseKey]) || $oneRM > $exerciseMaxes[$exerciseKey]) {
                $exerciseMaxes[$exerciseKey] = $oneRM;
            }
        }

        // Calculate relative strength: sum of best 1RMs / body weight
        $totalOneRM = array_sum($exerciseMaxes);
        $relativeStrength = $bodyWeight > 0 ? $totalOneRM / $bodyWeight : 0;

        // Scale to a more meaningful range (multiply by 100 for better UX)
        return $relativeStrength * 100;
    }

    /**
     * Get volume data by muscle group for a given period.
     */
    private function getMuscleGroupVolumes(Carbon $fromDate, ?Carbon $toDate = null): array
    {
        $query = $this->getUserSetLogs()->where('workout_sessions.performed_at', '>=', $fromDate);

        if ($toDate) {
            $query->where('workout_sessions.performed_at', '<', $toDate);
        }

        $volumes = [];
        foreach ($query->get() as $set) {
            $muscleGroup = $set->muscle_group_name;
            $volume = $set->weight * $set->reps; // Volume = weight × reps

            if (! isset($volumes[$muscleGroup])) {
                $volumes[$muscleGroup] = 0;
            }
            $volumes[$muscleGroup] += $volume;
        }

        return $volumes;
    }

    /**
     * Convert volumes to percentages.
     */
    private function getMuscleGroupPercentages(array $volumes): array
    {
        $total = array_sum($volumes);
        $percentages = [];

        if ($total > 0) {
            foreach ($volumes as $group => $volume) {
                $percentages[strtolower($group)] = ($volume / $total) * 100;
            }
        }

        return $percentages;
    }

    /**
     * Calculate balance percentage using coverage and evenness (geometric mean).
     *
     * Coverage measures what fraction of the 17 tracked muscle groups have been
     * trained. Evenness uses normalized Shannon entropy to measure how uniformly
     * volume is distributed among the trained groups. The geometric mean ensures
     * both factors must be reasonably high for a good score, while avoiding the
     * old formula's problem of demanding perfect uniformity across all 17 groups.
     */
    private function calculateBalancePercentage(array $muscleGroupPercentages): float
    {
        $totalGroups = count($this->getRequiredMuscleGroups());

        $trainedPercentages = array_filter($muscleGroupPercentages, fn ($p) => $p > 0);
        $trainedCount = count($trainedPercentages);

        if ($trainedCount === 0) {
            return 0;
        }

        $coverage = $trainedCount / $totalGroups;

        $evenness = 0.0;
        if ($trainedCount >= 2) {
            $total = array_sum($trainedPercentages);
            $entropy = 0.0;
            foreach ($trainedPercentages as $pct) {
                $proportion = $pct / $total;
                if ($proportion > 0) {
                    $entropy -= $proportion * log($proportion);
                }
            }
            $maxEntropy = log($trainedCount);
            $evenness = $maxEntropy > 0 ? $entropy / $maxEntropy : 0.0;
        }

        $balance = sqrt($coverage * $evenness) * 100;

        return min(100.0, max(0.0, $balance));
    }

    /**
     * @return string[]
     */
    private function getRequiredMuscleGroups(): array
    {
        return [
            'chest', 'lats', 'upper back', 'lower back',
            'front delts', 'side delts', 'rear delts', 'traps',
            'biceps', 'triceps', 'forearms',
            'quadriceps', 'hamstrings', 'glutes', 'calves',
            'abs', 'obliques',
        ];
    }

    /**
     * Determine strength level based on score and demographics.
     */
    private function determineStrengthLevel(float $score, $profile): string
    {
        // Basic thresholds - in a real app, these would be more sophisticated
        // and factor in age, gender, training experience, etc.

        $thresholds = $this->getStrengthThresholds($profile);

        if ($score >= $thresholds['advanced']) {
            return 'ADVANCED';
        } elseif ($score >= $thresholds['intermediate']) {
            return 'INTERMEDIATE';
        } else {
            return 'BEGINNER';
        }
    }

    /**
     * Get strength thresholds based on user demographics.
     */
    private function getStrengthThresholds($profile): array
    {
        $base = ['beginner' => 0, 'intermediate' => 200, 'advanced' => 400];

        // Adjust thresholds based on demographics
        if ($profile) {
            // Gender adjustments
            if ($profile->gender?->value === 'female') {
                $base['intermediate'] *= 0.8;
                $base['advanced'] *= 0.8;
            }

            // Age adjustments
            if ($profile->age && $profile->age > 50) {
                $multiplier = max(0.7, 1 - (($profile->age - 50) * 0.01));
                $base['intermediate'] *= $multiplier;
                $base['advanced'] *= $multiplier;
            }
        }

        return $base;
    }

    /**
     * Determine balance level based on percentage.
     *
     * Thresholds are calibrated against the coverage × evenness formula:
     * - EXCELLENT (≥80): 14+ groups with good evenness, or all 17 moderately even
     * - GOOD (≥60): ~10-13 groups with reasonable evenness (typical PPL split)
     * - FAIR (≥40): 6-9 groups, or more groups with skewed distribution
     * - NEEDS_IMPROVEMENT (<40): fewer than 6 groups, or extreme concentration
     */
    private function determineBalanceLevel(float $percentage): string
    {
        if ($percentage >= 80) {
            return 'EXCELLENT';
        } elseif ($percentage >= 60) {
            return 'GOOD';
        } elseif ($percentage >= 40) {
            return 'FAIR';
        } else {
            return 'NEEDS_IMPROVEMENT';
        }
    }

    /**
     * Get default strength score for users without sufficient data.
     */
    private function getDefaultStrengthScore(): array
    {
        return [
            'current' => 0,
            'level' => 'BEGINNER',
            'recent_gain' => 0,
            'gain_period' => 'last_30_days',
        ];
    }

    /**
     * Calculate strength percentile compared to similar users in the same partner.
     */
    private function calculateStrengthPercentile(float $score, $profile): ?int
    {
        // Return null if user has no partner
        if (! $this->user->partner_id) {
            return null;
        }

        // Return null if user has no profile or weight
        if (! $profile || ! $profile->weight) {
            return null;
        }

        // Get comparable users (same partner, similar demographics)
        $comparableUsers = User::where('partner_id', $this->user->partner_id)
            ->where('id', '!=', $this->user->id)
            ->whereHas('profile', function ($query) use ($profile) {
                $query->whereNotNull('weight');

                // Match gender
                if ($profile->gender) {
                    $query->where('gender', $profile->gender->value);
                }

                // Match training experience
                if ($profile->training_experience) {
                    $query->where('training_experience', $profile->training_experience->value);
                }

                // Match age range (±5 years)
                if ($profile->age) {
                    $query->whereBetween('age', [$profile->age - 5, $profile->age + 5]);
                }
            })
            ->with('profile')
            ->get();

        // Filter users with sufficient workout data (at least 5 completed workouts in last 30 days)
        $recent30Days = Carbon::now()->subDays(30);
        $validUsers = [];

        foreach ($comparableUsers as $user) {
            $workoutCount = WorkoutSession::where('user_id', $user->id)
                ->where('status', WorkoutSessionStatus::Completed)
                ->where('performed_at', '>=', $recent30Days)
                ->count();

            if ($workoutCount >= 5) {
                $validUsers[] = $user;
            }
        }

        // Need at least 10 comparable users for meaningful percentile
        if (count($validUsers) < 10) {
            return null;
        }

        // Calculate strength scores for all comparable users
        $scores = [];
        foreach ($validUsers as $user) {
            $userSets = $this->getUserSetLogsForUser($user->id, $recent30Days);
            $userScore = $this->calculateRelativeStrengthScore($userSets, $user->profile->weight);
            $scores[] = $userScore;
        }

        // Count users with lower scores
        $usersWithLowerScore = 0;
        foreach ($scores as $userScore) {
            if ($userScore < $score) {
                $usersWithLowerScore++;
            }
        }

        // Calculate percentile: (users with lower score / total comparable users) * 100
        $percentile = (int) round(($usersWithLowerScore / count($scores)) * 100);

        return $percentile;
    }

    /**
     * Calculate strength scores by muscle group.
     */
    private function calculateMuscleGroupStrengthScores($sets, float $bodyWeight): array
    {
        $muscleGroupScores = [];
        $muscleGroupMaxes = [];

        foreach ($sets->get() as $set) {
            $muscleGroup = strtolower($set->muscle_group_name);

            // Calculate 1RM using Epley formula
            $oneRM = $set->weight * (1 + ($set->reps / 30));

            // Keep track of best 1RM for each muscle group
            if (! isset($muscleGroupMaxes[$muscleGroup]) || $oneRM > $muscleGroupMaxes[$muscleGroup]) {
                $muscleGroupMaxes[$muscleGroup] = $oneRM;
            }
        }

        // Calculate relative strength per muscle group (only if at least 3 sets)
        $muscleGroupSetCounts = [];
        foreach ($sets->get() as $set) {
            $muscleGroup = strtolower($set->muscle_group_name);
            $muscleGroupSetCounts[$muscleGroup] = ($muscleGroupSetCounts[$muscleGroup] ?? 0) + 1;
        }

        foreach ($muscleGroupMaxes as $muscleGroup => $maxOneRM) {
            // Only include muscle groups with at least 3 sets
            if (($muscleGroupSetCounts[$muscleGroup] ?? 0) >= 3) {
                $relativeStrength = $bodyWeight > 0 ? $maxOneRM / $bodyWeight : 0;
                $muscleGroupScores[$muscleGroup] = (int) round($relativeStrength * 100);
            }
        }

        // Aggregate related groups for display
        return $this->aggregateMuscleGroupScores($muscleGroupScores);
    }

    /**
     * Aggregate granular muscle groups into display groups.
     */
    private function aggregateMuscleGroupScores(array $scores): array
    {
        $aggregated = [];

        // Chest
        if (isset($scores['chest'])) {
            $aggregated['chest'] = $scores['chest'];
        }

        // Back (combine lats, upper back, lower back)
        $backScores = [];
        foreach (['lats', 'upper back', 'lower back'] as $group) {
            if (isset($scores[$group])) {
                $backScores[] = $scores[$group];
            }
        }
        if (! empty($backScores)) {
            $aggregated['back'] = (int) round(array_sum($backScores) / count($backScores));
        }

        // Shoulders (combine front delts, side delts, rear delts)
        $shoulderScores = [];
        foreach (['front delts', 'side delts', 'rear delts'] as $group) {
            if (isset($scores[$group])) {
                $shoulderScores[] = $scores[$group];
            }
        }
        if (! empty($shoulderScores)) {
            $aggregated['shoulders'] = (int) round(array_sum($shoulderScores) / count($shoulderScores));
        }

        // Arms (combine biceps, triceps, forearms)
        $armScores = [];
        foreach (['biceps', 'triceps', 'forearms'] as $group) {
            if (isset($scores[$group])) {
                $armScores[] = $scores[$group];
            }
        }
        if (! empty($armScores)) {
            $aggregated['arms'] = (int) round(array_sum($armScores) / count($armScores));
        }

        // Legs (combine quadriceps, hamstrings, glutes, calves)
        $legScores = [];
        foreach (['quadriceps', 'hamstrings', 'glutes', 'calves'] as $group) {
            if (isset($scores[$group])) {
                $legScores[] = $scores[$group];
            }
        }
        if (! empty($legScores)) {
            $aggregated['legs'] = (int) round(array_sum($legScores) / count($legScores));
        }

        // Core (combine abs, obliques)
        $coreScores = [];
        foreach (['abs', 'obliques'] as $group) {
            if (isset($scores[$group])) {
                $coreScores[] = $scores[$group];
            }
        }
        if (! empty($coreScores)) {
            $aggregated['core'] = (int) round(array_sum($coreScores) / count($coreScores));
        }

        return $aggregated;
    }

    /**
     * Get user's set logs for a specific user ID (used for percentile calculations).
     */
    private function getUserSetLogsForUser(int $userId, Carbon $fromDate)
    {
        return DB::table('workout_session_set_logs')
            ->join('workout_sessions', 'workout_session_set_logs.workout_session_id', '=', 'workout_sessions.id')
            ->join('workout_exercises', 'workout_session_set_logs.exercise_id', '=', 'workout_exercises.id')
            ->join('exercise_muscle_group', 'workout_exercises.id', '=', 'exercise_muscle_group.exercise_id')
            ->join('muscle_groups', 'exercise_muscle_group.muscle_group_id', '=', 'muscle_groups.id')
            ->where('workout_sessions.user_id', $userId)
            ->where('workout_sessions.status', WorkoutSessionStatus::Completed)
            ->where('workout_sessions.performed_at', '>=', $fromDate)
            ->whereNotNull('workout_session_set_logs.weight')
            ->where('workout_session_set_logs.weight', '>', 0)
            ->where('workout_session_set_logs.reps', '>', 0)
            ->where('exercise_muscle_group.is_primary', true)
            ->select([
                'workout_session_set_logs.*',
                'workout_sessions.performed_at',
                'workout_exercises.name as exercise_name',
                'muscle_groups.name as muscle_group_name',
                'muscle_groups.body_region',
            ]);
    }

    /**
     * Calculate balance percentile compared to similar users in the same partner.
     */
    private function calculateBalancePercentile(float $balancePercentage, $profile): ?int
    {
        // Return null if user has no partner
        if (! $this->user->partner_id) {
            return null;
        }

        // Return null if user has no profile
        if (! $profile) {
            return null;
        }

        // Get comparable users (same partner, similar demographics)
        $comparableUsers = User::where('partner_id', $this->user->partner_id)
            ->where('id', '!=', $this->user->id)
            ->whereHas('profile', function ($query) use ($profile) {
                // Match gender
                if ($profile->gender) {
                    $query->where('gender', $profile->gender->value);
                }

                // Match training experience
                if ($profile->training_experience) {
                    $query->where('training_experience', $profile->training_experience->value);
                }

                // Match age range (±5 years)
                if ($profile->age) {
                    $query->whereBetween('age', [$profile->age - 5, $profile->age + 5]);
                }
            })
            ->with('profile')
            ->get();

        // Filter users with sufficient workout data (at least 5 completed workouts in last 30 days)
        $recent30Days = Carbon::now()->subDays(30);
        $validUsers = [];

        foreach ($comparableUsers as $user) {
            $workoutCount = WorkoutSession::where('user_id', $user->id)
                ->where('status', WorkoutSessionStatus::Completed)
                ->where('performed_at', '>=', $recent30Days)
                ->count();

            if ($workoutCount >= 5) {
                $validUsers[] = $user;
            }
        }

        // Need at least 10 comparable users for meaningful percentile
        if (count($validUsers) < 10) {
            return null;
        }

        // Calculate balance percentages for all comparable users
        $balancePercentages = [];
        foreach ($validUsers as $user) {
            $userVolumes = $this->getMuscleGroupVolumesForUser($user->id, $recent30Days);
            $userPercentages = $this->getMuscleGroupPercentages($userVolumes);
            $userBalance = $this->calculateBalancePercentage($userPercentages);
            $balancePercentages[] = $userBalance;
        }

        // Count users with lower balance
        $usersWithLowerBalance = 0;
        foreach ($balancePercentages as $userBalance) {
            if ($userBalance < $balancePercentage) {
                $usersWithLowerBalance++;
            }
        }

        // Calculate percentile: (users with lower balance / total comparable users) * 100
        $percentile = (int) round(($usersWithLowerBalance / count($balancePercentages)) * 100);

        return $percentile;
    }

    /**
     * Get muscle group volumes for a specific user ID (used for percentile calculations).
     */
    private function getMuscleGroupVolumesForUser(int $userId, Carbon $fromDate): array
    {
        $query = $this->getUserSetLogsForUser($userId, $fromDate);

        $volumes = [];
        foreach ($query->get() as $set) {
            $muscleGroup = $set->muscle_group_name;
            $volume = $set->weight * $set->reps; // Volume = weight × reps

            if (! isset($volumes[$muscleGroup])) {
                $volumes[$muscleGroup] = 0;
            }
            $volumes[$muscleGroup] += $volume;
        }

        return $volumes;
    }

    /**
     * Get daily breakdown for a week.
     */
    private function getDailyBreakdown(Carbon $weekStart, Carbon $weekEnd, $sessions): array
    {
        $dailyData = [];

        // Initialize all 7 days with zeros
        for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
            $date = $weekStart->copy()->addDays($dayOfWeek);
            $dailyData[$dayOfWeek] = [
                'day_of_week' => $dayOfWeek,
                'date' => $date->format('Y-m-d'),
                'volume' => 0,
                'workouts' => 0,
                'time_minutes' => 0,
            ];
        }

        // Conversion factor: 1 kg = 2.20462 lbs
        $kgToLbs = 2.20462;

        // Process sessions and aggregate by day
        foreach ($sessions as $session) {
            // Carbon's dayOfWeekIso returns 1-7 where 1 = Monday, 7 = Sunday
            // Convert to 0-6 where 0 = Monday, 6 = Sunday
            $dayOfWeek = $session->performed_at->dayOfWeekIso - 1;

            // Calculate volume for this session
            // Weight is stored in KG, convert to lbs for API response
            $sessionVolume = 0;
            foreach ($session->setLogs as $setLog) {
                $sessionVolume += ($setLog->weight * $setLog->reps) * $kgToLbs;
            }

            // Calculate duration
            $durationMinutes = 0;
            if ($session->performed_at && $session->completed_at) {
                $durationMinutes = $session->performed_at->diffInMinutes($session->completed_at);
            }

            $dailyData[$dayOfWeek]['volume'] += $sessionVolume;
            $dailyData[$dayOfWeek]['workouts'] += 1;
            $dailyData[$dayOfWeek]['time_minutes'] += $durationMinutes;
        }

        // Convert to indexed array and round values
        $result = [];
        foreach ($dailyData as $day) {
            $result[] = [
                'day_of_week' => $day['day_of_week'],
                'date' => $day['date'],
                'volume' => (int) round($day['volume']),
                'workouts' => $day['workouts'],
                'time_minutes' => (int) round($day['time_minutes']),
            ];
        }

        return $result;
    }

    /**
     * Get historical weekly progress data.
     */
    private function getHistoricalWeeklyProgress(int $weeks = 8): array
    {
        $endDate = Carbon::now()->endOfWeek(); // End of current week (Sunday)
        $startDate = Carbon::now()->subWeeks($weeks - 1)->startOfWeek(); // Start of N weeks ago (Monday)

        // Get all completed workouts in the date range
        $workouts = WorkoutSession::where('user_id', $this->user->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->get(['performed_at']);

        // Group by week manually to avoid MySQL-specific functions
        $weeklyCounts = [];
        foreach ($workouts as $workout) {
            $weekStart = Carbon::parse($workout->performed_at)->startOfWeek();
            $weekKey = $weekStart->format('Y-m-d');
            if (! isset($weeklyCounts[$weekKey])) {
                $weeklyCounts[$weekKey] = 0;
            }
            $weeklyCounts[$weekKey]++;
        }

        // Build complete array for last N weeks with zeros for weeks without workouts
        $result = [];
        $currentWeekStart = $startDate->copy();

        for ($i = 0; $i < $weeks; $i++) {
            $weekKey = $currentWeekStart->format('Y-m-d');
            $weekLabel = $currentWeekStart->format('M d');

            $result[] = [
                'week' => $weekLabel,
                'workouts' => $weeklyCounts[$weekKey] ?? 0,
            ];

            $currentWeekStart->addWeek();
        }

        return $result;
    }
}
