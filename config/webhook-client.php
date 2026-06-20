<?php

return [
    'configs' => [
        [
            'name' => 'revenuecat',
            'signing_secret' => env('REVENUECAT_WEBHOOK_SECRET'),
            'signature_header_name' => 'Authorization',
            'signature_validator' => \App\Webhooks\RevenueCat\RevenueCatSignatureValidator::class,
            'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
            'webhook_response' => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'store_headers' => [],
            'store_attachments' => false,
            'process_webhook_job' => \App\Webhooks\RevenueCat\ProcessRevenueCatWebhook::class,
        ],
    ],

    'delete_after_days' => 90,

    'add_unique_token_to_route_name' => false,
];
