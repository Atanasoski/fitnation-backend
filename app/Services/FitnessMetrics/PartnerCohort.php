<?php

namespace App\Services\FitnessMetrics;

use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;

/**
 * Where a user ranks against comparable people at the same partner.
 *
 * Comparable means: same partner, same gender, same training experience, within
 * five years of age, and training often enough recently to be worth ranking
 * against. Below ten such people there is no cohort and no answer — a
 * percentile drawn from three gym-mates says nothing.
 *
 * The metric being ranked is the caller's business; this module only knows how
 * to find the cohort and count how much of it a score beats. Strength and
 * balance used to each carry their own forty-line copy of this, identical but
 * for the scoring function.
 */
final class PartnerCohort
{
    /** Below this many comparable users, a percentile is noise. */
    private const MINIMUM_SIZE = 10;

    /** A cohort member has to have trained at least this often in the window. */
    private const MINIMUM_SESSIONS = 5;

    /**
     * The percentage of the cohort that `$score` beats, or null when there is
     * no cohort to speak of.
     *
     * @param  callable(User, Carbon): float  $scoreOf  the same calculation that produced `$score`, for a cohort member
     * @param  bool  $requireBodyWeight  whether the scoring function divides by body weight, and so cannot rank anyone who has not recorded one
     */
    public static function percentile(
        User $user,
        float $score,
        callable $scoreOf,
        bool $requireBodyWeight,
    ): ?int {
        $profile = $user->profile;

        if (! $user->partner_id || ! $profile) {
            return null;
        }

        if ($requireBodyWeight && ! $profile->weight) {
            return null;
        }

        $since = Carbon::now()->subDays(CompletedSessions::RECENT_DAYS);
        $members = self::members($user, $profile, $requireBodyWeight, $since);

        if (count($members) < self::MINIMUM_SIZE) {
            return null;
        }

        $beaten = 0;
        foreach ($members as $member) {
            if ($scoreOf($member, $since) < $score) {
                $beaten++;
            }
        }

        return (int) round(($beaten / count($members)) * 100);
    }

    /**
     * @return array<int, User>
     */
    private static function members(User $user, UserProfile $profile, bool $requireBodyWeight, Carbon $since): array
    {
        $comparable = User::where('partner_id', $user->partner_id)
            ->where('id', '!=', $user->id)
            ->whereHas('profile', function ($query) use ($profile, $requireBodyWeight) {
                if ($requireBodyWeight) {
                    $query->whereNotNull('weight');
                }

                if ($profile->gender) {
                    $query->where('gender', $profile->gender->value);
                }

                if ($profile->training_experience) {
                    $query->where('training_experience', $profile->training_experience->value);
                }

                if ($profile->age) {
                    $query->whereBetween('age', [$profile->age - 5, $profile->age + 5]);
                }
            })
            ->with('profile')
            ->get();

        return $comparable
            ->filter(fn (User $member) => CompletedSessions::sessionCountSince($member->id, $since) >= self::MINIMUM_SESSIONS)
            ->values()
            ->all();
    }
}
