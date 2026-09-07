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
 * The first mail about something other than the user's account, so the first
 * with an unsubscribe: a signed one-click link in the footer and in the
 * List-Unsubscribe headers mail clients read.
 */
class WeeklySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

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

        return new Content(
            view: 'emails.weekly-summary',
            text: 'emails.weekly-summary-text',
            with: [
                'subject' => $this->subjectLine(),
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
