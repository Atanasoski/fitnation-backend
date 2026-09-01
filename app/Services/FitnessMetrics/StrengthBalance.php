<?php

namespace App\Services\FitnessMetrics;

use App\Models\User;
use Carbon\Carbon;

/**
 * How evenly a user spreads their training volume across the body.
 *
 * Two things have to be true to be balanced: you have to train most of the
 * seventeen tracked muscle groups (coverage), and you have to give the ones you
 * do train comparable attention (evenness, measured as normalised Shannon
 * entropy). The score is the geometric mean of the two, so neither can carry
 * the other — training three groups perfectly evenly is not balance, and nor is
 * touching all seventeen while putting nine tenths of the volume into one.
 *
 * Everything here is in Canonical Units (ADR-0001).
 */
final class StrengthBalance
{
    /**
     * The groups a balanced athlete trains. Granular on purpose: "back" as one
     * group would let rows alone pass for a trained back.
     *
     * @var array<int, string>
     */
    public const MUSCLE_GROUPS = [
        'chest', 'lats', 'upper back', 'lower back',
        'front delts', 'side delts', 'rear delts', 'traps',
        'biceps', 'triceps', 'forearms',
        'quadriceps', 'hamstrings', 'glutes', 'calves',
        'abs', 'obliques',
    ];

    /**
     * @return array{percentage: int, level: string, recent_change: int, muscle_groups: array<string, int>, percentile?: int}
     */
    public function for(User $user): array
    {
        $user->loadMissing('profile');

        $recent = Carbon::now()->subDays(CompletedSessions::RECENT_DAYS);
        $previous = Carbon::now()->subDays(CompletedSessions::RECENT_DAYS * 2);

        $shares = self::sharesWithUntrainedGroups(
            CompletedSessions::volumeByMuscleGroup($user->id, $recent)
        );

        $percentage = self::percentage($shares);

        $previousPercentage = self::percentage(
            self::shares(CompletedSessions::volumeByMuscleGroup($user->id, $previous, $recent))
        );

        $result = [
            'percentage' => (int) round($percentage),
            'level' => self::level($percentage),
            'recent_change' => (int) round($percentage - $previousPercentage),
            'muscle_groups' => $shares,
        ];

        $percentile = PartnerCohort::percentile(
            $user,
            $percentage,
            fn (User $member, Carbon $since) => self::percentage(
                self::shares(CompletedSessions::volumeByMuscleGroup($member->id, $since))
            ),
            requireBodyWeight: false,
        );

        if ($percentile !== null) {
            $result['percentile'] = $percentile;
        }

        return $result;
    }

    /**
     * Volume per muscle group as a percentage of total volume, lower-cased.
     * Groups with no volume are absent, not zero.
     *
     * @param  array<string, float>  $volumes
     * @return array<string, float>
     */
    public static function shares(array $volumes): array
    {
        $total = array_sum($volumes);

        if ($total <= 0) {
            return [];
        }

        $shares = [];
        foreach ($volumes as $group => $volume) {
            $shares[strtolower($group)] = ($volume / $total) * 100;
        }

        return $shares;
    }

    /**
     * The same shares, rounded to whole percent and padded out with a zero for
     * every tracked group the user did not train.
     *
     * This is the reported shape: the untrained groups are the point of the
     * metric, so leaving them out would hide the answer.
     *
     * Ordered by MUSCLE_GROUPS rather than by whatever order the rows came back
     * in. The volume query has no ORDER BY, so the reported key order used to
     * be the query planner's choice and moved when the index it picked did.
     *
     * @param  array<string, float>  $volumes
     * @return array<string, int>
     */
    public static function sharesWithUntrainedGroups(array $volumes): array
    {
        $shares = [];

        foreach (self::shares($volumes) as $group => $share) {
            $shares[$group] = (int) round($share);
        }

        $ordered = [];
        foreach (self::MUSCLE_GROUPS as $group) {
            $ordered[$group] = $shares[$group] ?? 0;
        }

        // Volume logged against a muscle group outside the tracked list still
        // counts toward the score, so it is still reported; it just has no slot
        // of its own to sit in.
        return $ordered + $shares;
    }

    /**
     * Coverage × evenness, geometric mean, as a percentage.
     *
     * @param  array<string, int|float>  $shares  volume share per muscle group
     */
    public static function percentage(array $shares): float
    {
        $trained = array_filter($shares, fn ($share) => $share > 0);

        if ($trained === []) {
            return 0.0;
        }

        $coverage = count($trained) / count(self::MUSCLE_GROUPS);
        $balance = sqrt($coverage * self::evenness($trained)) * 100;

        return min(100.0, max(0.0, $balance));
    }

    /**
     * Normalised Shannon entropy of the trained groups: 1.0 when every trained
     * group got the same volume, approaching 0 as one of them takes over. A
     * single trained group has no distribution to be even about, so it scores 0
     * rather than a vacuous 1.
     *
     * @param  array<string, int|float>  $trained  non-empty, all shares > 0
     */
    private static function evenness(array $trained): float
    {
        if (count($trained) < 2) {
            return 0.0;
        }

        $total = array_sum($trained);
        $entropy = 0.0;

        foreach ($trained as $share) {
            $proportion = $share / $total;
            $entropy -= $proportion * log($proportion);
        }

        return $entropy / log(count($trained));
    }

    /**
     * Thresholds are calibrated against the coverage × evenness formula:
     * - EXCELLENT (≥80): 14+ groups with good evenness, or all 17 moderately even
     * - GOOD (≥60): ~10-13 groups with reasonable evenness (typical PPL split)
     * - FAIR (≥40): 6-9 groups, or more groups with skewed distribution
     * - NEEDS_IMPROVEMENT (<40): fewer than 6 groups, or extreme concentration
     */
    public static function level(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'EXCELLENT',
            $percentage >= 60 => 'GOOD',
            $percentage >= 40 => 'FAIR',
            default => 'NEEDS_IMPROVEMENT',
        };
    }
}
