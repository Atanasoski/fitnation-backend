<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Notifications\UnfinishedAccountCandidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One step of the Unfinished Account ladder, as an email. What it says follows
 * the step and what the user is stuck at ('unverified' or 'not_onboarded');
 * the primary action is the verification link for the former and the store
 * link (/get) for the latter. Partner-branded like VerifyEmailMail.
 *
 * Service mail about an account the user created: no unsubscribe link. The
 * ladder's three-and-done is the courtesy.
 */
class UnfinishedAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $step,
        public string $stuckAt,
        public string $primaryUrl,
        public string $appUrl,
    ) {
        $this->to($user->email, $user->name);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.unfinished-account',
            text: 'emails.unfinished-account-text',
            with: [
                'subject' => $this->subjectLine(),
                'lead' => $this->lead(),
                'button' => $this->copy('button.'.$this->stuckAt),
                'secondary' => $this->stuckAt === UnfinishedAccountCandidate::UNVERIFIED
                    ? $this->copy('secondary.unverified')
                    : null,
            ],
        );
    }

    public function subjectLine(): string
    {
        return $this->copy("{$this->step}.{$this->stuckAt}.subject");
    }

    public function lead(): string
    {
        return $this->copy("{$this->step}.{$this->stuckAt}.lead");
    }

    public function partnerName(): string
    {
        return $this->user->partner?->name ?? config('app.name');
    }

    private function copy(string $key): string
    {
        return __("notifications.unfinished_account.{$key}", ['partner' => $this->partnerName()]);
    }
}
