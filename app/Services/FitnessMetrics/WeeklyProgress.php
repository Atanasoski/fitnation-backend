<?php

namespace App\Services\FitnessMetrics;

use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * How a user's last full training week compared with the one before it, plus
 * the run-up to both.
 *
 * "Current week" here means the last *completed* week, not the one in progress
 * — a Tuesday is not a fair comparison against a finished week, and the number
 * would fall every Monday and climb back by Sunday if it were.
 *
 * Everything here is in Canonical Units (ADR-0001).
 */
final class WeeklyProgress
{
    private const HISTORY_WEEKS = 8;

    /**
     * @return array{percentage: int, trend: string, current_week_workouts: int, previous_week_workouts: int, current_week_volume?: int, previous_week_volume?: int, volume_difference?: int, volume_difference_percent?: int, current_week_time_minutes?: int, daily_breakdown?: array<int, array<string, mixed>>, historical_weeks?: array<int, array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $currentWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $currentWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        $current = $this->sessionsBetween($user, $currentWeekStart, $currentWeekEnd);
        $previous = $this->sessionsBetween(
            $user,
            Carbon::now()->subWeeks(2)->startOfWeek(),
            Carbon::now()->subWeeks(2)->endOfWeek(),
        );

        $currentVolume = self::volume($current);
        $previousVolume = self::volume($previous);
        $volumeDifference = $currentVolume - $previousVolume;

        $percentage = self::percentageChange($current->count(), $previous->count());

        $result = [
            'percentage' => (int) round($percentage),
            'trend' => match (true) {
                $percentage > 0 => 'up',
                $percentage < 0 => 'down',
                default => 'same',
            },
            'current_week_workouts' => $current->count(),
            'previous_week_workouts' => $previous->count(),
        ];

        if ($currentVolume > 0 || $previousVolume > 0) {
            $result['current_week_volume'] = (int) round($currentVolume);
            $result['previous_week_volume'] = (int) round($previousVolume);
            $result['volume_difference'] = (int) round($volumeDifference);
            $result['volume_difference_percent'] = (int) round(
                self::percentageChange($currentVolume, $previousVolume)
            );
        }

        $timeMinutes = $current->sum(fn (WorkoutSession $session) => self::durationMinutes($session));

        if ($timeMinutes > 0) {
            $result['current_week_time_minutes'] = (int) round($timeMinutes);
        }

        $dailyBreakdown = self::dailyBreakdown($currentWeekStart, $current);

        if (! empty($dailyBreakdown)) {
            $result['daily_breakdown'] = $dailyBreakdown;
        }

        // The payload's `week` is the label, "Mar 02" — not a date, despite the
        // name, and not the same `week` the users.show chart sends (which is the
        // Y-m-d). Both are shipped contracts; historicalWeeks() below names its
        // own keys unambiguously so neither caller has to guess.
        $historicalWeeks = array_map(
            fn (array $week) => ['week' => $week['label'], 'workouts' => $week['workouts']],
            $this->historicalWeeks($user, self::HISTORY_WEEKS),
        );

        if (! empty($historicalWeeks)) {
            $result['historical_weeks'] = $historicalWeeks;
        }

        return $result;
    }

    /**
     * Completed workouts per week for the last N weeks, oldest first, with a
     * zero for every week the user did not train — a gap in the middle of a
     * chart is data, and dropping the row would slide the rest of the series
     * along and misdate it.
     *
     * The current, incomplete week is the last entry.
     *
     * @return array<int, array{week_start: string, label: string, workouts: int}>
     */
    public function historicalWeeks(User $user, int $weeks): array
    {
        $startDate = Carbon::now()->subWeeks($weeks - 1)->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        // Grouped in PHP rather than SQL: week-of-year functions differ between
        // MySQL and SQLite, and this runs against both.
        $counts = CompletedSessions::sessions($user->id)
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->get(['performed_at'])
            ->countBy(fn (WorkoutSession $session) => $session->performed_at->startOfWeek()->format('Y-m-d'));

        $result = [];
        $weekStart = $startDate->copy();

        for ($i = 0; $i < $weeks; $i++) {
            $key = $weekStart->format('Y-m-d');

            $result[] = [
                'week_start' => $key,
                'label' => $weekStart->format('M d'),
                'workouts' => $counts->get($key, 0),
            ];

            $weekStart->addWeek();
        }

        return $result;
    }

    /**
     * Volume, workouts and time for each of the seven days of a week, Monday
     * first, days without training included as zeros.
     *
     * @param  Collection<int, WorkoutSession>  $sessions
     * @return array<int, array{day_of_week: int, date: string, volume: int, workouts: int, time_minutes: int}>
     */
    public static function dailyBreakdown(Carbon $weekStart, Collection $sessions): array
    {
        $days = [];

        for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
            $days[$dayOfWeek] = [
                'day_of_week' => $dayOfWeek,
                'date' => $weekStart->copy()->addDays($dayOfWeek)->format('Y-m-d'),
                'volume' => 0.0,
                'workouts' => 0,
                'time_minutes' => 0.0,
            ];
        }

        foreach ($sessions as $session) {
            // dayOfWeekIso is 1 (Monday) to 7 (Sunday); the breakdown is 0-6.
            $dayOfWeek = $session->performed_at->dayOfWeekIso - 1;

            $days[$dayOfWeek]['volume'] += self::sessionVolume($session);
            $days[$dayOfWeek]['workouts']++;
            $days[$dayOfWeek]['time_minutes'] += self::durationMinutes($session);
        }

        return array_map(fn (array $day) => [
            'day_of_week' => $day['day_of_week'],
            'date' => $day['date'],
            'volume' => (int) round($day['volume']),
            'workouts' => $day['workouts'],
            'time_minutes' => (int) round($day['time_minutes']),
        ], array_values($days));
    }

    /**
     * Growth of `$current` over `$previous`, as a percentage. Coming up from
     * nothing counts as +100% rather than as an infinity.
     */
    public static function percentageChange(float $current, float $previous): float
    {
        if ($previous > 0) {
            return (($current - $previous) / $previous) * 100;
        }

        return $current > 0 ? 100.0 : 0.0;
    }

    /**
     * @param  Collection<int, WorkoutSession>  $sessions
     */
    private static function volume(Collection $sessions): float
    {
        return (float) $sessions->sum(fn (WorkoutSession $session) => self::sessionVolume($session));
    }

    /**
     * Weight × reps over every set logged in the session.
     */
    private static function sessionVolume(WorkoutSession $session): float
    {
        return (float) $session->setLogs->sum(fn ($setLog) => $setLog->weight * $setLog->reps);
    }

    private static function durationMinutes(WorkoutSession $session): float
    {
        if (! $session->performed_at || ! $session->completed_at) {
            return 0.0;
        }

        return (float) $session->performed_at->diffInMinutes($session->completed_at);
    }

    /**
     * @return Collection<int, WorkoutSession>
     */
    private function sessionsBetween(User $user, Carbon $from, Carbon $to): Collection
    {
        return CompletedSessions::sessions($user->id)
            ->whereBetween('performed_at', [$from, $to])
            ->with('setLogs')
            ->get();
    }
}
