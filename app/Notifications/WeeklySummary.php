<?php

namespace App\Notifications;

use App\Mail\WeeklySummaryMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The Weekly Summary (CONTEXT.md): last week against the one before, by email.
 *
 * Email only — the app has no week-summary surface to open onto, so no push.
 * The database row is the sent record App\Services\Notifications\WeeklySummaries
 * reads, and it keeps the numbers the user was told, so a future in-app inbox
 * can show the same thing the mail said. Those numbers are resolved once, by
 * the Mailable, which is where Canonical Units become the user's.
 */
class WeeklySummary extends Notification implements ShouldQueue
{
    use Queueable;

    private ?WeeklySummaryMail $mail = null;

    /**
     * @param  array<string, mixed>  $progress  WeeklyProgress::for() as of the user's Monday, in Canonical Units
     */
    public function __construct(public readonly array $progress) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toMail($notifiable)->numbers();
    }

    /**
     * Built once per send; the unsubscribe link it carries is signed on each build.
     */
    public function toMail(object $notifiable): WeeklySummaryMail
    {
        if ($this->mail?->user->is($notifiable)) {
            return $this->mail;
        }

        /** @var User $notifiable */
        $notifiable->loadMissing('partner.identity', 'profile');

        return $this->mail = new WeeklySummaryMail(
            $notifiable,
            $this->progress,
            self::unsubscribeUrlFor($notifiable),
            route('app.get'),
        );
    }

    /**
     * The signed, non-expiring link that turns this email off for the user —
     * a summary may sit unread for weeks and the link has to keep working.
     */
    public static function unsubscribeUrlFor(User $user): string
    {
        return URL::signedRoute('email.weekly-summary.unsubscribe', ['user' => $user->id]);
    }
}
