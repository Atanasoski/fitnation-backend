<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rapidapi' => [
        'key' => env('RAPIDAPI_KEY'),
        'muscle_image' => [
            'host' => 'muscle-group-image-generator.p.rapidapi.com',
            'base_url' => 'https://muscle-group-image-generator.p.rapidapi.com/getIndividualColorImage',
        ],
    ],

    'google' => [
        'client_id'          => env('GOOGLE_WEB_CLIENT_ID'),
        'ios_client_id'      => env('GOOGLE_IOS_CLIENT_ID'),
        'android_client_id'  => env('GOOGLE_ANDROID_CLIENT_ID'),
    ],

    'apple' => [
        'bundle_id'  => env('APPLE_BUNDLE_ID', 'com.fitnation.app'),
        'service_id' => env('APPLE_SERVICE_ID'), // web Service ID, e.g. com.fitnation.app.web
    ],

];
