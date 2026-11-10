<?php

namespace App\Services\WorkoutGenerator;

use App\Enums\MovementClass;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressionCalculatorService
{
    public function __construct(
        private PrescriptionCalculator $prescriptionCalculator
    ) {}

    /**
     * Decimal places SetLog casts `weight` to. Kept in step with that cast.
     */
    private const WEIGHT_PRECISION = 1;

    /**
     * The three verdicts on a working session's performance (see verdict()).
     */
    private const TOPPED = 'topped';

    private const WORKING = 'working';

    private const BELOW_MIN = 'below_min';

    /**
     * Calculate target sets, reps, and weight for an exercise based on user history
     */
    public function calculateTargets(Exercise $exercise, User $user, ?TrainingExperience $experience = null): array
    {
        return $this->calculateTargetsFrom(
            $exercise,
            $user,
            $experience,
            $this->getLastPerformance($exercise, $user)
        );
    }

    /**
     * Calculate targets from an already-resolved last performance.
     *
     * Callers that serialize many exercises at once resolve the performances in
     * one batch (see getLastPerformanceForExercises) and pass each one in here,
     * so the history lookup does not repeat per exercise.
     *
     * @param  array<string, mixed>|null  $lastPerformance  null means "no history", not "not yet resolved"
     */
    public function calculateTargetsFrom(Exercise $exercise, User $user, ?TrainingExperience $experience, ?array $lastPerformance): array
    {
        $experience ??= TrainingExperience::Beginner;
        $defaultTargets = $this->getDefaultTargets($experience, $exercise, $user);

        // Logged performance still outranks the default Prescription below.
        $prescription = $this->matrixPrescription($exercise, $user);

        if ($this->isBodyweight($exercise)) {
            $totalRepsPrevious = $lastPerformance ? array_sum($lastPerformance['reps'] ?? []) : null;
            $totalRepsTarget = $totalRepsPrevious !== null ? $totalRepsPrevious + 1 : null;

            return [
                'progression_mode' => 'total_reps',
                'target_sets' => $lastPerformance['sets'] ?? $defaultTargets['target_sets'],
                'min_target_reps' => null,
                'max_target_reps' => null,
                'target_weight' => null,
                'total_reps_previous' => $totalRepsPrevious,
                'total_reps_target' => $totalRepsTarget,
                'rest_seconds' => $lastPerformance['rest_seconds'] ?? $prescription['rest_seconds'],
                // Total-reps progression has no rep range to judge against.
                'progression_status' => $lastPerformance ? 'working' : 'no_history',
            ];
        }

        if (! $lastPerformance) {
            return [
                'progression_mode' => 'double_progression',
                'target_sets' => $defaultTargets['target_sets'],
                'min_target_reps' => $prescription['min_target_reps'],
                'max_target_reps' => $prescription['max_target_reps'],
                'target_weight' => $defaultTargets['target_weight'],
                'total_reps_previous' => null,
                'total_reps_target' => null,
                'rest_seconds' => $prescription['rest_seconds'],
                'progression_status' => 'no_history',
            ];
        }

        // One verdict on the last working session (ADR-0011) drives both the
        // raise and the status. Each set is judged against its own Logged
        // Range; the matrix range only stands in for a set that carries none.
        $targets = $this->applyProgressiveOverload($lastPerformance, $exercise, $prescription['max_target_reps']);

        return [
            'progression_mode' => 'double_progression',
            'target_sets' => $targets['sets'],
            'min_target_reps' => $prescription['min_target_reps'],
            'max_target_reps' => $prescription['max_target_reps'],
            'target_weight' => $targets['weight'],
            'total_reps_previous' => null,
            'total_reps_target' => null,
            'rest_seconds' => $lastPerformance['rest_seconds'] ?? $prescription['rest_seconds'],
            'progression_status' => $this->getProgressionStatus(
                $lastPerformance,
                $prescription['min_target_reps'],
                $prescription['max_target_reps'],
                $exercise
            ),
        ];
    }

    /**
     * Get last performance for an exercise
     */
    public function getLastPerformance(Exercise $exercise, User $user): ?array
    {
        $lastSetLog = $user->workoutSessions()
            ->completed()
            // Deload sessions are recovery, not performance history (ADR-0008)
            ->where('is_deload', false)
            ->whereHas('setLogs', function ($q) use ($exercise) {
                $q->where('exercise_id', $exercise->id);
            })
            ->with(['setLogs' => function ($q) use ($exercise) {
                // Each set's Logged Range comes from its own row, read in the
                // same query as the sets (as the batched lookup does).
                $q->leftJoin('workout_session_exercises as logged_row', 'logged_row.id', '=', 'workout_session_set_logs.workout_session_exercise_id')
                    ->select([
                        'workout_session_set_logs.*',
                        'logged_row.min_target_reps as logged_min_reps',
                        'logged_row.max_target_reps as logged_max_reps',
                    ])
                    ->where('workout_session_set_logs.exercise_id', $exercise->id)
                    ->orderBy('workout_session_set_logs.set_number')
                    // tiebreak for one exercise on two rows, so this agrees with the batched lookup
                    ->orderBy('workout_session_set_logs.id');
            }])
            ->orderBy('completed_at', 'desc')
            ->orderBy('id', 'desc') // tiebreak, so this agrees with the batched window below
            ->first();

        if (! $lastSetLog || $lastSetLog->setLogs->isEmpty()) {
            return null;
        }

        return $this->shapeLastPerformance($lastSetLog->setLogs, $exercise, $user);
    }

    /**
     * Batched getLastPerformance() for many exercises at once.
     *
     * Resolves, per exercise, the sets from the user's most recent completed
     * session containing that exercise — the same row the single-exercise
     * lookup picks — in one query rather than one query per exercise.
     *
     * Uses a window function, so this requires MySQL 8.0+ / MariaDB 10.2+
     * (or SQLite 3.25+ for local dev).
     *
     * @param  \Illuminate\Support\Collection<int, Exercise>|array<int, Exercise>  $exercises
     * @return array<int, array<string, mixed>> keyed by exercise_id; exercises with no history are absent
     */
    public function getLastPerformanceForExercises(Collection|array $exercises, User $user): array
    {
        $exercises = collect($exercises)->keyBy('id');

        if ($exercises->isEmpty()) {
            return [];
        }

        // Rank each (exercise, completed session) pair newest-first and keep only
        // the winner. DENSE_RANK (not ROW_NUMBER) so every set of the winning
        // session survives the filter, since they share the same rank key.
        $ranked = DB::table('workout_session_set_logs as set_logs')
            ->join('workout_sessions as sessions', 'sessions.id', '=', 'set_logs.workout_session_id')
            // Each set's Logged Range: the rep range of the row it was logged against
            ->leftJoin('workout_session_exercises as logged_row', 'logged_row.id', '=', 'set_logs.workout_session_exercise_id')
            ->where('sessions.user_id', $user->id)
            ->where('sessions.status', WorkoutSessionStatus::Completed->value)
            // Deload sessions are recovery, not performance history (ADR-0008)
            ->where('sessions.is_deload', false)
            ->whereIn('set_logs.exercise_id', $exercises->keys()->all())
            ->select([
                'set_logs.id',
                'set_logs.exercise_id',
                'set_logs.workout_session_exercise_id',
                'set_logs.set_number',
                'set_logs.weight',
                'set_logs.reps',
                'set_logs.rest_seconds',
                'logged_row.min_target_reps as logged_min_reps',
                'logged_row.max_target_reps as logged_max_reps',
                DB::raw('DENSE_RANK() OVER (PARTITION BY set_logs.exercise_id ORDER BY sessions.completed_at DESC, sessions.id DESC) as session_rank'),
            ]);

        $rows = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('ranked.session_rank', 1)
            ->orderBy('ranked.exercise_id')
            ->orderBy('ranked.set_number')
            ->orderBy('ranked.id') // same tiebreak as the single lookup
            ->get();

        $performances = [];

        foreach ($rows->groupBy('exercise_id') as $exerciseId => $setLogs) {
            $exercise = $exercises->get((int) $exerciseId);

            if (! $exercise) {
                continue;
            }

            $performances[(int) $exerciseId] = $this->shapeLastPerformance($setLogs, $exercise, $user);
        }

        return $performances;
    }

    /**
     * Build the last-performance array from one session's sets for one exercise.
     *
     * Shared by the single and batched lookups so both report identical numbers.
     *
     * Alongside each set's weight and reps it carries the set's Logged Range
     * (`logged_ranges`, [min, max] per set) and the row it was logged against
     * (`session_exercise_ids`). A row that stored no range, or a legacy set
     * with no row, is judged against the matrix range at read time — the
     * range the member was shown for it (ADR-0011).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $setLogs  ordered by set_number, then id
     * @return array<string, mixed>
     */
    private function shapeLastPerformance(Collection $setLogs, Exercise $exercise, User $user): array
    {
        [$matrixMin, $matrixMax] = $this->matrixRange($exercise, $user);

        // SetLog casts weight to decimal:1. The batched lookup reads the column
        // through the query builder, which bypasses model casts, so normalise
        // here — otherwise the two paths report different weights for the same
        // set and progression drifts by up to a full equipment increment.
        $weights = $setLogs
            ->pluck('weight')
            ->map(fn ($weight) => round((float) $weight, self::WEIGHT_PRECISION))
            ->all();

        $normalizedWeightKeys = array_map(
            fn (float $weight) => number_format($weight, 2, '.', ''),
            $weights
        );
        $weightCounts = array_count_values($normalizedWeightKeys);
        arsort($weightCounts);
        $mostCommonWeight = (float) array_key_first($weightCounts);

        $reps = $setLogs
            ->pluck('reps')
            ->map(fn ($repCount) => (int) $repCount)
            ->all();

        $restSeconds = $setLogs
            ->pluck('rest_seconds')
            ->filter(fn ($rest) => $rest !== null)
            ->map(fn ($rest) => (int) $rest)
            ->first();

        // Same rule as the displayed target (SessionProgression::targetsFor):
        // a stored bound wins when it holds anything, otherwise the matrix's.
        $loggedRanges = $setLogs
            ->map(fn ($set) => [
                (int) ($set->logged_min_reps ?: $matrixMin),
                (int) ($set->logged_max_reps ?: $matrixMax),
            ])
            ->values()
            ->all();

        $sessionExerciseIds = $setLogs
            ->map(fn ($set) => $set->workout_session_exercise_id === null ? null : (int) $set->workout_session_exercise_id)
            ->values()
            ->all();

        return [
            'weight' => $mostCommonWeight,
            'sets' => $setLogs->count(),
            // null when no set logged a rest: the consumer falls back to the
            // default Prescription's goal-scaled rest, not the raw Default Rest.
            'rest_seconds' => $restSeconds,
            'weights' => $weights,
            'reps' => $reps,
            'logged_ranges' => $loggedRanges,
            'session_exercise_ids' => $sessionExerciseIds,
        ];
    }

    /**
     * The mechanics-matrix rep range (ADR-0009) for the exercise's own class
     * and the member's current goal.
     *
     * @return array{0: int, 1: int}
     */
    private function matrixRange(Exercise $exercise, User $user): array
    {
        $prescription = $this->matrixPrescription($exercise, $user);

        return [(int) $prescription['min_target_reps'], (int) $prescription['max_target_reps']];
    }

    /**
     * The default Prescription (ADR-0009) for the exercise's own class and
     * the member's current goal: rep range from the compound/isolation ×
     * goal matrix, rest from the exercise's Default Rest scaled by the goal.
     *
     * @return array{min_target_reps: int, max_target_reps: int, rest_seconds: int}
     */
    private function matrixPrescription(Exercise $exercise, User $user): array
    {
        $movementPatternCode = $exercise->movementPattern?->code;

        return $this->prescriptionCalculator->defaults(
            MovementClass::fromPattern($movementPatternCode),
            $user->fitnessGoal(),
            $exercise->default_rest_sec,
            $movementPatternCode,
        );
    }

    /**
     * Apply progressive overload based on last performance: one equipment
     * step up when the session topped (see verdict()).
     *
     * @param  int  $maxTargetReps  the ceiling for a set that carries no Logged Range of its own
     */
    public function applyProgressiveOverload(array $lastPerformance, ?Exercise $exercise = null, int $maxTargetReps = 12): array
    {
        $weight = $lastPerformance['weight'];
        $sets = $lastPerformance['sets'] ?? 3;
        $increment = $exercise ? $this->getWeightIncrement($exercise) : 2.5;
        $targetWeight = $weight;

        if (
            // The floor plays no part in whether a session topped.
            $this->verdict($lastPerformance, 0, $maxTargetReps) === self::TOPPED
            && $weight > 0
            && $increment > 0
        ) {
            $targetWeight = $this->roundToEquipmentIncrement($weight + $increment, $exercise);
        }

        return [
            'weight' => $targetWeight,
            'sets' => $sets,
        ];
    }

    /**
     * One equipment step below a weight, offered when the last session was too
     * hard (status below_min). The mirror of applyProgressiveOverload()'s raise:
     * the same increment, landed on what the equipment can load. Null when the
     * equipment has no steps (bodyweight, bands) or the step would reach zero.
     */
    public function lowerByOneStep(float $weight, Exercise $exercise): ?float
    {
        $increment = $this->getWeightIncrement($exercise);

        if ($increment <= 0 || $weight - $increment <= 0) {
            return null;
        }

        return $this->roundToEquipmentIncrement($weight - $increment, $exercise);
    }

    /**
     * Determine current progression status for frontend messaging: the
     * session's verdict (see verdict()) as the app reads it.
     *
     * @param  int  $minTargetReps  the floor for a set that carries no Logged Range of its own
     * @param  int  $maxTargetReps  the ceiling for a set that carries no Logged Range of its own
     */
    public function getProgressionStatus(?array $lastPerformance, int $minTargetReps, int $maxTargetReps, ?Exercise $exercise = null): string
    {
        if ($exercise && $this->isBodyweight($exercise)) {
            return $lastPerformance ? 'working' : 'no_history';
        }

        if (! $lastPerformance) {
            return 'no_history';
        }

        return match ($this->verdict($lastPerformance, $minTargetReps, $maxTargetReps)) {
            self::TOPPED => 'ready',
            self::BELOW_MIN => 'below_min',
            default => 'working',
        };
    }

    /**
     * The one verdict on a working session's performance (ADR-0011), judged
     * set by set against each set's own Logged Range:
     *
     * - topped: every set reached the top of its Logged Range, and the weight
     *   held across each row's sets (a lighter back-off row does not count
     *   against the top set, but dropping load within a row does);
     * - below_min: any set fell under its Logged Range's minimum;
     * - working: anything else.
     *
     * A set with no Logged Range (a performance built by hand) is judged
     * against the matrix range.
     *
     * @param  array<string, mixed>  $lastPerformance
     */
    private function verdict(array $lastPerformance, int $matrixMin, int $matrixMax): string
    {
        $reps = array_values($lastPerformance['reps'] ?? []);
        $weights = array_values($lastPerformance['weights'] ?? []);
        $ranges = $lastPerformance['logged_ranges'] ?? [];
        $rowIds = $lastPerformance['session_exercise_ids'] ?? [];

        $topped = $reps !== [] && $weights !== [];
        $belowMin = false;

        foreach ($reps as $index => $repCount) {
            [$minReps, $maxReps] = $ranges[$index] ?? [$matrixMin, $matrixMax];

            $topped = $topped && $repCount >= $maxReps;
            $belowMin = $belowMin || $repCount < $minReps;
        }

        $weightsByRow = [];
        foreach ($weights as $index => $weight) {
            $weightsByRow[(string) ($rowIds[$index] ?? '')][] = $weight;
        }

        foreach ($weightsByRow as $rowWeights) {
            $topped = $topped && count(array_unique($rowWeights, SORT_NUMERIC)) === 1;
        }

        return match (true) {
            $topped => self::TOPPED,
            $belowMin => self::BELOW_MIN,
            default => self::WORKING,
        };
    }

    /**
     * Determine if exercise progression should use total reps mode.
     */
    public function isBodyweight(Exercise $exercise): bool
    {
        return $this->getWeightIncrement($exercise) <= 0;
    }

    /**
     * Round a weight to the nearest realistic equipment increment
     */
    private function roundToEquipmentIncrement(float $weight, ?Exercise $exercise): float
    {
        if (! $exercise) {
            // Fallback to 2.5kg if no exercise provided
            return round($weight / 2.5) * 2.5;
        }

        $increment = $this->getWeightIncrement($exercise);

        // If increment is 0 (bodyweight/band), return 0
        if ($increment <= 0) {
            return 0;
        }

        return max(0, round($weight / $increment) * $increment);
    }

    /**
     * Experience-level defaults for what the Prescription does not cover:
     * sets and the starting-weight estimate. Rep range and rest come from
     * PrescriptionCalculator (ADR-0009).
     */
    private function getDefaultTargets(TrainingExperience $experience, ?Exercise $exercise = null, ?User $user = null): array
    {
        $targetWeight = 0;

        if ($exercise && $user) {
            $targetWeight = $this->estimateStartingWeight($exercise, $user, $experience);
        }

        return [
            'target_sets' => $experience === TrainingExperience::Beginner ? 3 : 4,
            'target_weight' => $targetWeight,
        ];
    }

    /**
     * Estimate starting weight based on user profile and exercise characteristics.
     *
     * Applies modifiers for movement pattern, gender, experience, equipment type,
     * and angle. Dumbbell weights are estimated per dumbbell (not total).
     */
    private function estimateStartingWeight(Exercise $exercise, User $user, TrainingExperience $experience): float
    {
        $profile = $user->profile;

        if (! $profile || ! $profile->weight) {
            return 0; // Can't estimate without body weight
        }

        $bodyWeight = (float) $profile->weight;
        $gender = $profile->gender ?? \App\Enums\Gender::Male;

        // Adjust for gender (females typically ~60-70% of male strength ratios)
        $genderModifier = match ($gender) {
            \App\Enums\Gender::Female => 0.65,
            \App\Enums\Gender::Other => 0.80,
            default => 1.0,
        };

        // Adjust for experience
        $experienceModifier = match ($experience) {
            TrainingExperience::Beginner => 0.6,
            TrainingExperience::Intermediate => 1.0,
            TrainingExperience::Advanced => 1.3,
        };

        // An exercise its pattern misjudges has its own ratio (per implement),
        // which stands in for the pattern, equipment and angle factors
        $ratio = $this->startingWeightRatio($exercise);
        if ($ratio !== null) {
            return $this->roundToEquipmentIncrement($bodyWeight * $ratio * $genderModifier * $experienceModifier, $exercise);
        }

        $movementCode = $exercise->movementPattern?->code ?? 'UNKNOWN';
        $equipmentCode = $exercise->equipmentType?->code ?? 'UNKNOWN';
        $angleCode = $exercise->angle?->code ?? null;

        // Base multiplier by movement pattern (as fraction of body weight)
        $baseMultiplier = $this->getBaseMultiplier($movementCode);

        // Adjust for equipment type (dumbbell = per hand, cable lighter, etc.)
        $equipmentModifier = $this->getEquipmentModifier($equipmentCode);

        // Adjust for angle (overhead press lighter than bench, etc.)
        $angleModifier = $this->getAngleModifier($movementCode, $angleCode);

        $estimatedWeight = $bodyWeight * $baseMultiplier * $genderModifier
            * $experienceModifier * $equipmentModifier * $angleModifier;

        // Round to realistic weight increments based on equipment type
        return $this->roundToEquipmentIncrement($estimatedWeight, $exercise);
    }

    /**
     * The exercise's own starting-weight ratio from
     * workout_generator.starting_weight_ratios, matched by name, if it has one.
     */
    private function startingWeightRatio(Exercise $exercise): ?float
    {
        $ratios = array_change_key_case(config('workout_generator.starting_weight_ratios', []), CASE_LOWER);
        $ratio = $ratios[strtolower((string) $exercise->name)] ?? null;

        return $ratio === null ? null : (float) $ratio;
    }

    /**
     * Get the appropriate weight increment based on equipment type.
     *
     * Barbell: 2.5kg (smallest common plate pair = 1.25kg × 2)
     * Dumbbell: Fixed increments, typically 2kg or 2.5kg jumps (per dumbbell)
     * Cable/Machine: Usually 2.5kg or 5kg pin increments
     * Kettlebell: Fixed weights, typically 4kg jumps
     */
    public function getWeightIncrement(Exercise $exercise): float
    {
        $equipmentCode = $exercise->equipmentType?->code ?? $exercise->equipmentType?->name ?? 'UNKNOWN';

        return match (strtoupper($equipmentCode)) {
            'BARBELL' => 2.5,           // 1.25kg plates on each side
            'DUMBBELL' => 2.0,          // Fixed dumbbell increments (per dumbbell)
            'CABLE' => 2.5,             // Cable stack increments
            'MACHINE' => 5.0,           // Machine weight stack increments
            'SMITH' => 2.5,             // Similar to barbell
            'KETTLEBELL' => 4.0,        // Standard KB jumps (8, 12, 16, 20, 24...)
            'BAND' => 0.0,              // Bands don't have weight
            'BODYWEIGHT' => 0.0,        // No external weight
            'TRX' => 0.0,              // No external weight
            'MEDICINE_BALL' => 1.0,    // Fixed weight, smallest ~1kg increment
            'LANDMINE' => 2.5,         // Barbell in landmine attachment, same plate increments
            default => 2.5,             // Safe default
        };
    }

    /**
     * Get equipment modifier for starting weight estimation.
     *
     * Dumbbell weights are per dumbbell (~45% of barbell equivalent due to
     * stabilization demands). Cable/machine weights differ from free weights.
     */
    private function getEquipmentModifier(string $equipmentCode): float
    {
        return match (strtoupper($equipmentCode)) {
            'BARBELL' => 1.0,          // Baseline: total weight on bar
            'DUMBBELL' => 0.45,        // Per dumbbell (~45% of barbell, stabilization cost)
            'CABLE' => 0.55,           // Cable stacks lighter due to pulley mechanics
            'MACHINE' => 0.80,         // Guided path, but stack calibration varies
            'SMITH' => 0.85,           // Guided barbell, slightly less than free barbell
            'KETTLEBELL' => 0.45,      // Single implement, similar to per-dumbbell
            'MEDICINE_BALL' => 0.10,   // Very light implements
            'LANDMINE' => 0.75,        // Barbell in landmine, angled leverage reduces load
            'BODYWEIGHT' => 1.0,       // N/A (weight increment is 0)
            'TRX' => 1.0,             // N/A (weight increment is 0)
            'BAND' => 1.0,            // N/A (weight increment is 0)
            default => 0.70,           // Conservative default
        };
    }

    /**
     * Get angle modifier for starting weight estimation.
     *
     * Only affects upper body presses, rows, and flies where the angle
     * significantly changes the leverage and difficulty.
     * e.g. Overhead press (~65% of bench press), incline (~85% of flat).
     */
    private function getAngleModifier(string $movementCode, ?string $angleCode): float
    {
        // Angle only significantly affects upper body pressing, rowing, and flies
        if (! in_array($movementCode, ['PRESS', 'ROW', 'FLY'])) {
            return 1.0;
        }

        return match ($angleCode) {
            'FLAT' => 1.0,             // Strongest position for press/fly
            'HORIZONTAL' => 1.0,       // Strongest position for rows
            'INCLINE' => 0.85,         // ~15% less than flat
            'DECLINE' => 1.05,         // Slightly stronger than flat for press
            'VERTICAL' => 0.65,        // Overhead press ~65% of bench press
            'LOW_TO_HIGH' => 0.60,     // Landmine/low-to-high angle press
            'HIGH_TO_LOW' => 0.80,     // High-to-low cable work
            default => 0.85,           // Conservative default
        };
    }

    /**
     * Base multipliers as fraction of body weight (for intermediate male)
     * These are realistic starting points based on fitness standards
     */
    private function getBaseMultiplier(string $movementCode): float
    {
        return match ($movementCode) {
            // Compound Lower Body (strongest)
            'SQUAT', 'LEG_PRESS' => 1.0,
            'HINGE' => 1.1,                           // Deadlift patterns
            'HIP_THRUST_BRIDGE' => 0.8,
            'LUNGE_SPLIT_SQUAT' => 0.4,               // Per leg
            'BACK_EXTENSION' => 0.5,

            // Compound Upper Body
            'PRESS' => 0.65,                          // Bench press type
            'ROW' => 0.55,
            'PULL_VERTICAL' => 0.5,                   // Lat pulldown
            'DIP', 'PUSHUP' => 0.0,                   // Bodyweight
            'PULLOVER_STRAIGHT_ARM' => 0.25,

            // Isolation
            'ELBOW_FLEXION' => 0.15,                  // Bicep curls
            'ELBOW_EXTENSION' => 0.18,                // Tricep exercises
            'FLY' => 0.20,                            // Chest flies
            'KNEE_EXTENSION' => 0.35,                 // Leg extension
            'KNEE_FLEXION' => 0.30,                   // Leg curl
            'CALF_RAISE' => 0.7,
            'REAR_DELT_FLY', 'FACE_PULL' => 0.12,
            'SHOULDER_RAISE' => 0.12,                 // Lateral and front raises
            'HIP_ABDUCTION' => 0.20,
            'CARRY' => 0.35,

            // Core (often bodyweight or light)
            'TRUNK_FLEXION', 'ROTATION', 'ANTI_ROTATION' => 0.0,

            default => 0.30,                          // Conservative fallback
        };
    }
}
