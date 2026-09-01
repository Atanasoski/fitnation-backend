<?php

namespace App\Services\FitnessMetrics;

use App\Enums\Gender;
use App\Models\User;
use Carbon\Carbon;

/**
 * How strong a user is, relative to their own body weight.
 *
 * The score is the sum of their best estimated one-rep max per exercise over
 * the last 30 days, divided by their body weight, times 100 — so it is a
 * multiple of body weight rather than an absolute load, and a 60kg lifter is
 * not automatically behind a 100kg one. One-rep maxes are estimated with the
 * Epley formula, weight × (1 + reps/30).
 *
 * A user with no recorded body weight has no score, because the whole quantity
 * is a ratio to it.
 *
 * Everything here is in Canonical Units (ADR-0001).
 */
final class StrengthScore
{
    /** Below this many sets in the window, a muscle group's score is guesswork. */
    private const MINIMUM_SETS_PER_MUSCLE_GROUP = 3;

    /**
     * Granular muscle groups, folded into the groups the UI shows. A display
     * group scores the mean of whichever of its parts were trained.
     *
     * @var array<string, array<int, string>>
     */
    private const DISPLAY_GROUPS = [
        'chest' => ['chest'],
        'back' => ['lats', 'upper back', 'lower back'],
        'shoulders' => ['front delts', 'side delts', 'rear delts'],
        'arms' => ['biceps', 'triceps', 'forearms'],
        'legs' => ['quadriceps', 'hamstrings', 'glutes', 'calves'],
        'core' => ['abs', 'obliques'],
    ];

    /**
     * @return array{current: int, level: string, recent_gain: int, gain_period: string, percentile?: int, muscle_groups?: array<string, int>}
     */
    public function for(User $user): array
    {
        $user->loadMissing('profile');
        $profile = $user->profile;

        if (! $profile || ! $profile->weight) {
            return self::none();
        }

        $bodyWeight = (float) $profile->weight;
        $recent = Carbon::now()->subDays(CompletedSessions::RECENT_DAYS);
        $previous = Carbon::now()->subDays(CompletedSessions::RECENT_DAYS * 2);

        $recentSets = CompletedSessions::setLogs($user->id, $recent)->get();
        $previousSets = CompletedSessions::setLogs($user->id, $previous, $recent)->get();

        $current = self::relativeStrength($recentSets, $bodyWeight);

        $result = [
            'current' => (int) round($current),
            'level' => self::level($current, $profile->gender, $profile->age),
            'recent_gain' => (int) round($current - self::relativeStrength($previousSets, $bodyWeight)),
            'gain_period' => 'last_30_days',
        ];

        $percentile = PartnerCohort::percentile(
            $user,
            $current,
            fn (User $member, Carbon $since) => self::relativeStrength(
                CompletedSessions::setLogs($member->id, $since)->get(),
                (float) $member->profile->weight,
            ),
            requireBodyWeight: true,
        );

        if ($percentile !== null) {
            $result['percentile'] = $percentile;
        }

        $muscleGroups = self::muscleGroupScores($recentSets, $bodyWeight);

        if (! empty($muscleGroups)) {
            $result['muscle_groups'] = $muscleGroups;
        }

        return $result;
    }

    /**
     * Sum of the best estimated one-rep max per exercise, as a percentage of
     * body weight.
     *
     * Best *per exercise*, not per set: five sets of the same lift describe one
     * capability, and counting each of them would reward volume as if it were
     * strength.
     *
     * @param  iterable<object{weight: mixed, reps: mixed, exercise_name: string}>  $sets
     */
    public static function relativeStrength(iterable $sets, float $bodyWeight): float
    {
        if ($bodyWeight <= 0) {
            return 0.0;
        }

        $bestPerExercise = [];

        foreach ($sets as $set) {
            $oneRepMax = self::oneRepMax((float) $set->weight, (int) $set->reps);

            $bestPerExercise[$set->exercise_name] = max(
                $bestPerExercise[$set->exercise_name] ?? 0.0,
                $oneRepMax,
            );
        }

        return (array_sum($bestPerExercise) / $bodyWeight) * 100;
    }

    /**
     * Epley: an estimate of the single heaviest rep a set implies.
     */
    public static function oneRepMax(float $weight, int $reps): float
    {
        return $weight * (1 + ($reps / 30));
    }

    public static function level(float $score, ?Gender $gender = null, ?int $age = null): string
    {
        $thresholds = self::thresholds($gender, $age);

        return match (true) {
            $score >= $thresholds['advanced'] => 'ADVANCED',
            $score >= $thresholds['intermediate'] => 'INTERMEDIATE',
            default => 'BEGINNER',
        };
    }

    /**
     * The score a user of these demographics has to reach for each level.
     *
     * Deliberately crude — a flat base, scaled for gender and for age past 50.
     * Real strength standards vary by lift and by training age; these do not
     * pretend to.
     *
     * @return array{beginner: float, intermediate: float, advanced: float}
     */
    public static function thresholds(?Gender $gender = null, ?int $age = null): array
    {
        $scale = 1.0;

        if ($gender === Gender::Female) {
            $scale *= 0.8;
        }

        if ($age !== null && $age > 50) {
            $scale *= max(0.7, 1 - (($age - 50) * 0.01));
        }

        return [
            'beginner' => 0.0,
            'intermediate' => 200 * $scale,
            'advanced' => 400 * $scale,
        ];
    }

    /**
     * A score per display muscle group, from the best one-rep max recorded
     * against each granular group.
     *
     * @param  iterable<object{weight: mixed, reps: mixed, muscle_group_name: string}>  $sets
     * @return array<string, int>
     */
    public static function muscleGroupScores(iterable $sets, float $bodyWeight): array
    {
        if ($bodyWeight <= 0) {
            return [];
        }

        $best = [];
        $setCounts = [];

        foreach ($sets as $set) {
            $group = strtolower($set->muscle_group_name);

            $best[$group] = max(
                $best[$group] ?? 0.0,
                self::oneRepMax((float) $set->weight, (int) $set->reps),
            );
            $setCounts[$group] = ($setCounts[$group] ?? 0) + 1;
        }

        $scores = [];
        foreach ($best as $group => $oneRepMax) {
            if (($setCounts[$group] ?? 0) >= self::MINIMUM_SETS_PER_MUSCLE_GROUP) {
                $scores[$group] = (int) round(($oneRepMax / $bodyWeight) * 100);
            }
        }

        return self::asDisplayGroups($scores);
    }

    /**
     * @param  array<string, int>  $scores  keyed by granular muscle group
     * @return array<string, int> keyed by display group
     */
    public static function asDisplayGroups(array $scores): array
    {
        $aggregated = [];

        foreach (self::DISPLAY_GROUPS as $display => $groups) {
            $present = array_values(array_intersect_key($scores, array_flip($groups)));

            if ($present !== []) {
                $aggregated[$display] = (int) round(array_sum($present) / count($present));
            }
        }

        return $aggregated;
    }

    /**
     * @return array{current: int, level: string, recent_gain: int, gain_period: string}
     */
    private static function none(): array
    {
        return [
            'current' => 0,
            'level' => 'BEGINNER',
            'recent_gain' => 0,
            'gain_period' => 'last_30_days',
        ];
    }
}
