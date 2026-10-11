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

    /*
    |--------------------------------------------------------------------------
    | Sign-up trial
    |--------------------------------------------------------------------------
    |
    | Days of app access a new user gets when onboarding completes, with no
    | card and no store involved (User::startSignupTrial, called from
    | WelcomePlanGenerationService). It reuses grace_period_ends_at, with
    | free_access_kind `signup_trial`, so the paywall takes over when the date
    | passes. 0 disables it. GET /user reports it as signup_trial_days. The
    | launch grace command is unaffected: it only touches users who were never
    | granted anything.
    |
    */

    'signup_trial_days' => (int) env('SUBSCRIPTIONS_SIGNUP_TRIAL_DAYS', 7),

];
