<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Off, the Expo channel logs what it would have sent and sends nothing.
    | Scheduled work still runs so it can be observed. Keep this false on every
    | host that is not production: all build profiles share one Expo project.
    |
    */

    'enabled' => env('NOTIFICATIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Expo Push Service
    |--------------------------------------------------------------------------
    |
    | The relay every push goes through. The access token is the project's
    | push-security token from expo.dev; with it set there, requests without it
    | are refused, so only this server can push to the app's tokens.
    |
    */

    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'send_url' => 'https://exp.host/--/api/v2/push/send',
        'receipts_url' => 'https://exp.host/--/api/v2/push/getReceipts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Which Devices to push to
    |--------------------------------------------------------------------------
    |
    | Devices register the EAS build profile they were built with. Set this to
    | 'production' in production so a phone running a development build never
    | receives a production notification; leave it null elsewhere.
    |
    */

    'only_build_profile' => env('NOTIFICATIONS_BUILD_PROFILE'),

    /*
    |--------------------------------------------------------------------------
    | Timezone fallback
    |--------------------------------------------------------------------------
    |
    | The timezone a Device is assumed to be in when it never reported one.
    | Anything the domain schedules "at a local time" falls back to this.
    |
    */

    'default_timezone' => 'Europe/Skopje',

    /*
    |--------------------------------------------------------------------------
    | Inactivity Nudge
    |--------------------------------------------------------------------------
    |
    | Days of inactivity at which a nudge is sent, and the Device-local hour to
    | send it. Consumed by App\Services\Notifications\Inactivity.
    |
    */

    'inactivity' => [
        'ladder' => [3, 7, 14],
        'local_hour' => 18,
    ],

    /*
    |--------------------------------------------------------------------------
    | Where a tap lands
    |--------------------------------------------------------------------------
    |
    | Deep links the app routes on (front-end spec 0012). Phase one only ever
    | sends the dashboard.
    |
    */

    'urls' => [
        'dashboard' => 'fitnation://dashboard',
    ],

];
