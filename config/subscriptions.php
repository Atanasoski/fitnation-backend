<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | Off, nobody is gated: RequiresSubscription lets every authenticated
    | request through and User::entitlements() reports every entitlement for
    | everyone, so the app never shows a paywall. That lets the subscription
    | code deploy dark. Switch it on per environment once the stores and the
    | RevenueCat webhook are live. Webhooks are processed either way, so
    | subscription rows are already correct when the gate comes on.
    |
    */

    'enforced' => env('SUBSCRIPTIONS_ENFORCED', false),

];
