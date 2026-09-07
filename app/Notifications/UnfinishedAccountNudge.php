<?php

namespace App\Notifications;

use App\Mail\UnfinishedAccountMail;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;
use App\Services\Notifications\UnfinishedAccountCandidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "You never finished" — one step of the Unfinished Account ladder (CONTEXT.md).
 *
 * Email is the channel that reaches these users; the database row is the sent
 * record App\Services\Notifications\UnfinishedAccounts reads; the push no-ops
 * for the usual case of no Device and is there for the reinstall or
 * second-account cases where one exists. Every channel says the same thing —
 * the mail's subject and lead — so the copy is resolved once, by the Mailable.
 */
class UnfinishedAccountNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $step,
        public readonly string $stuckAt,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'expo'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = $this->toMail($notifiable);

        return [
            'step' => $this->step,
            'stuck_at' => $this->stuckAt,
            'title' => $mail->subjectLine(),
            'body' => $mail->lead(),
            'url' => self::appUrl(),
        ];
    }

    public function toMail(object $notifiable): UnfinishedAccountMail
    {
        /** @var User $notifiable */
        $notifiable->loadMissing('partner.identity');

        $primaryUrl = $this->stuckAt === UnfinishedAccountCandidate::UNVERIFIED
            ? VerifyEmail::urlFor($notifiable)
            : self::appUrl();

        return new UnfinishedAccountMail($notifiable, $this->step, $this->stuckAt, $primaryUrl, self::appUrl());
    }

    public function toExpo(object $notifiable): ExpoMessage
    {
        $mail = $this->toMail($notifiable);

        return new ExpoMessage(
            title: $mail->subjectLine(),
            body: $mail->lead(),
            data: ['url' => self::appUrl()],
        );
    }

    /**
     * Where every link lands: /get opens the store, and the app if installed.
     * Universal links are a later spec.
     */
    private static function appUrl(): string
    {
        return route('app.get');
    }
}
