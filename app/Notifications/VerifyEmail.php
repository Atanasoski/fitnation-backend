<?php

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Mail\Mailable;

class VerifyEmail extends BaseVerifyEmail
{
    /**
     * The signed, expiring link that verifies this user's address — the same
     * one the verification mail carries, for any other mail that wants to
     * offer it (the Unfinished Account nudge does).
     */
    public static function urlFor(MustVerifyEmail $user): string
    {
        return (new static)->verificationUrl($user);
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable): Mailable
    {
        $notifiable->loadMissing('partner.identity');

        $verificationUrl = $this->verificationUrl($notifiable);

        return new VerifyEmailMail($notifiable, $verificationUrl);
    }
}
