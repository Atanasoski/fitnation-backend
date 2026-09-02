<?php

namespace App\Providers;

use App\Notifications\Channels\ExpoChannel;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        if (App::environment('production')) {
            URL::forceScheme('https');
        }

        Notification::extend('expo', fn () => new ExpoChannel);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();

            if ($notifiable instanceof \App\Models\User && $notifiable->hasAnyRole(['admin', 'partner_admin'])) {
                return rtrim(config('app.url'), '/').'/reset-password/'.$token.'?email='.urlencode($email);
            }

            return rtrim(config('app.webapp_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($email);
        });

        // Update last_login_at when user logs in
        Event::listen(Login::class, function (Login $event) {
            $event->user->update(['last_login_at' => now()]);
        });
    }
}
