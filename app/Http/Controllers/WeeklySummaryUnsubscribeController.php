<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Turns the Weekly Summary email off for the user named in a signed link and
 * says so. No login: the signature is the proof, and it is checked by the
 * `signed` middleware before this runs. GET and POST do the same thing, so a
 * mail client's List-Unsubscribe-Post and a person's click land in one place.
 */
class WeeklySummaryUnsubscribeController extends Controller
{
    public const SETTING = 'weekly_summary_email';

    public function __invoke(User $user): View
    {
        $user->setNotificationSetting(self::SETTING, false);
        $user->save();

        return view('emails.unsubscribed', [
            'user' => $user->loadMissing('partner.identity'),
            'appUrl' => route('app.get'),
        ]);
    }
}
