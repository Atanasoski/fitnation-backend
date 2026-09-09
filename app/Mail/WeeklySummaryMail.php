<?php

namespace App\Mail;

use App\Models\User;
use App\Services\UnitConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The Weekly Summary (CONTEXT.md), as an email: how the week that just ended
 * went against the one before, from a Weekly Progress payload in Canonical
 * Units. This is the boundary where the volume becomes the user's Unit System
 * (ADR-0001) — a Training Weight total, whole, never with decimals — and where
 * the sentence for the trend is chosen. Partner-branded like the others.
 *
 * Two charts sit under the numbers, drawn as tables so every mail client shows
 * them: the finished weeks in the payload as columns of workouts, the week just
 * ended marked; and that week day by day, volume in the user's unit — or
 * minutes when nothing was lifted. The shapes are computed here (charts()),
 * so the template only draws and tests read the data, not the HTML.
 *
 * The first mail about something other than the user's account, so the first
 * with an unsubscribe: a signed one-click link in the footer and in the
 * List-Unsubscribe headers mail clients read.
 */
class WeeklySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /** The tallest column, in pixels; every other column scales to it. */
    private const BAR_HEIGHT = 72;

    private const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * @param  array<string, mixed>  $progress  WeeklyProgress::for() for the week that just ended
     */
    public function __construct(
        public User $user,
        public array $progress,
        public string $unsubscribeUrl,
        public string $appUrl,
    ) {
        $this->to($user->email, $user->name);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $numbers = $this->numbers();
        $charts = $this->charts();

        return new Content(
            view: 'emails.weekly-summary',
            text: 'emails.weekly-summary-text',
            with: [
                'subject' => $this->subjectLine(),
                'accent' => $this->user->partner?->identity?->primary_color ?? '#f86f33', // orange-500
                'charts' => $charts,
                'chartWeeksTitle' => $charts ? __('notifications.weekly_summary.chart_weeks', ['count' => count($charts['weeks'])]) : null,
                'chartDaysTitle' => __('notifications.weekly_summary.chart_days'),
                'weeksLine' => $charts ? implode(' · ', array_column($charts['weeks'], 'workouts')) : null,
                'daysLine' => $charts ? implode(' · ', array_map(
                    fn (array $day) => "{$day['label']} {$day['value_label']}",
                    array_filter($charts['days'], fn (array $day) => $day['value_label'] !== null),
                )) : null,
                'workouts' => $this->workoutsPhrase($numbers['workouts']),
                'workoutsDelta' => __('notifications.weekly_summary.workouts_delta', [
                    'delta' => self::signed($numbers['workouts'] - $numbers['previous_workouts']),
                ]),
                'volume' => $numbers['volume'] === null ? null : __('notifications.weekly_summary.volume', [
                    'volume' => number_format($numbers['volume']),
                    'unit' => $numbers['unit'],
                ]),
                'volumeDelta' => $numbers['volume_change_percent'] === null ? null : __('notifications.weekly_summary.volume_delta', [
                    'delta' => self::signed($numbers['volume_change_percent']),
                ]),
                'time' => __('notifications.weekly_summary.time', ['minutes' => $numbers['time_minutes']]),
                'trendLine' => __("notifications.weekly_summary.trend.{$numbers['trend']}"),
                'button' => __('notifications.weekly_summary.button'),
                'unsubscribe' => __('notifications.weekly_summary.unsubscribe'),
            ],
        );
    }

    public function subjectLine(): string
    {
        return __('notifications.weekly_summary.subject', [
            'workouts' => $this->workoutsPhrase($this->progress['current_week_workouts']),
        ]);
    }

    /**
     * What the user is told, as numbers: the sent record keeps these so a row
     * is a record of the mail, not just of the fact that one went out. Volume
     * is in the user's Unit System; null when neither week lifted anything.
     *
     * @return array{workouts: int, previous_workouts: int, volume: ?int, unit: string, volume_change_percent: ?int, time_minutes: int, trend: string}
     */
    public function numbers(): array
    {
        $unitSystem = $this->user->unitSystem();
        $workouts = $this->progress['current_week_workouts'];
        $previous = $this->progress['previous_week_workouts'];

        $volume = $this->progress['current_week_volume'] ?? null;

        return [
            'workouts' => $workouts,
            'previous_workouts' => $previous,
            'volume' => $volume === null ? null : app(UnitConversionService::class)->toDisplayTotal($volume, $unitSystem),
            'unit' => $unitSystem->weightUnit(),
            'volume_change_percent' => $this->progress['volume_difference_percent'] ?? null,
            'time_minutes' => $this->progress['current_week_time_minutes'] ?? 0,
            'trend' => $workouts === 0 && $previous > 0 ? 'zero' : $this->progress['trend'],
        ];
    }

    /**
     * The two charts, as bars: label, value, a value label for the bars the
     * story is about (null for the rest), and a height scaled to the tallest.
     * Null when the payload carries no series to draw.
     *
     * The weeks are the finished ones — the payload's last entry is the week in
     * progress, which is never the subject — with the week just ended marked
     * `current`. The days are that week, Monday first, measured in volume in
     * the user's Unit System, or in minutes when nothing was lifted.
     *
     * @return ?array{weeks: list<array{label: string, workouts: int, value_label: ?string, current: bool, height: int}>, days: list<array{label: string, value: int, value_label: ?string, height: int}>, days_unit: string}
     */
    public function charts(): ?array
    {
        $history = $this->progress['historical_weeks'] ?? [];
        $breakdown = $this->progress['daily_breakdown'] ?? [];

        if (count($history) < 2 || count($breakdown) !== 7) {
            return null;
        }

        $finished = array_slice($history, 0, -1);
        $last = count($finished) - 1;
        $heights = self::scaled(array_column($finished, 'workouts'));

        $weeks = [];
        foreach ($finished as $i => $week) {
            $weeks[] = [
                'label' => $week['week'],
                'workouts' => $week['workouts'],
                'value_label' => $i >= $last - 1 ? (string) $week['workouts'] : null,
                'current' => $i === $last,
                'height' => $heights[$i],
            ];
        }

        [$values, $unit] = $this->dayMeasure($breakdown);
        $heights = self::scaled($values);

        $days = [];
        foreach ($breakdown as $i => $day) {
            $days[] = [
                'label' => self::DAY_LABELS[$day['day_of_week']],
                'value' => $values[$i],
                'value_label' => $values[$i] > 0 ? number_format($values[$i]).($unit === 'min' ? ' min' : " {$unit}") : null,
                'height' => $heights[$i],
            ];
        }

        return ['weeks' => $weeks, 'days' => $days, 'days_unit' => $unit];
    }

    /**
     * What the day chart measures: volume in the user's unit when the week
     * lifted anything, else minutes trained, else workouts.
     *
     * @param  list<array{volume: int, time_minutes: int, workouts: int}>  $days
     * @return array{0: list<int>, 1: string}
     */
    private function dayMeasure(array $days): array
    {
        $conversion = app(UnitConversionService::class);
        $unitSystem = $this->user->unitSystem();

        if (array_sum(array_column($days, 'volume')) > 0) {
            return [
                array_map(fn (array $day) => $conversion->toDisplayTotal($day['volume'], $unitSystem), $days),
                $unitSystem->weightUnit(),
            ];
        }

        if (array_sum(array_column($days, 'time_minutes')) > 0) {
            return [array_map(fn (array $day) => (int) $day['time_minutes'], $days), 'min'];
        }

        return [array_map(fn (array $day) => (int) $day['workouts'], $days), 'workouts'];
    }

    /**
     * Bar heights, the tallest at BAR_HEIGHT and the rest in proportion.
     *
     * @param  list<int|float>  $values
     * @return list<int>
     */
    private static function scaled(array $values): array
    {
        $max = max([0, ...$values]);

        return array_map(fn ($value) => $max > 0 ? (int) round($value / $max * self::BAR_HEIGHT) : 0, $values);
    }

    private function workoutsPhrase(int $count): string
    {
        return trans_choice('notifications.weekly_summary.workouts', $count);
    }

    /**
     * "+2", "-1", "±0" — a change that reads as a change.
     */
    private static function signed(int $delta): string
    {
        return match (true) {
            $delta > 0 => "+{$delta}",
            $delta < 0 => (string) $delta,
            default => '±0',
        };
    }
}
