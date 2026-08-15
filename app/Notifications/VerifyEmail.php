<?php

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

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

    /**
     * Build the verification URL for the email button.
     *
     * The link points at the member web app (not the API host) so that on a
     * phone with the app installed it resolves as a universal/app link and
     * opens the app directly. The signature is generated for the API route
     * `api.verification.verify`; the web page or app forwards the id, hash,
     * and signed query string to that endpoint, so the query must be passed
     * through untouched.
     */
    protected function verificationUrl($notifiable): string
    {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        $signedApiUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            ['id' => $id, 'hash' => $hash]
        );

        $query = parse_url($signedApiUrl, PHP_URL_QUERY);

        return rtrim(config('app.webapp_url'), '/')."/verify-email/{$id}/{$hash}?{$query}";
    }
}
