<?php

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Mail\Mailable;

class VerifyEmail extends BaseVerifyEmail
{
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
