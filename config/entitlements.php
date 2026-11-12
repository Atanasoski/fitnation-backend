<?php

use App\Enums\Entitlement;

// Product ID (as RevenueCat sends it in the webhook) → the entitlements it grants.
//
// Google Play products carry their base plan: RevenueCat reports a Play
// subscription as `<product id>:<base plan id>`, and the base plans are named
// `monthly` and `yearly` in Play Console / RevenueCat. App Store products are
// the bare ID. Both spellings grant the same access.
//
// Each Paywall Discount tier (PaywallDiscountTier) sells its own monthly and
// yearly product, `com.fitnation.app.premium.discount<percent>.<period>`, with
// the same access as the standard products. On the App Store every tier has its
// own subscription group: iOS lets a subscriber switch to any plan in their
// group from Settings, so a discount in the standard group would be open to all.
return [
    'com.fitnation.app.premium.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly:yearly' => [Entitlement::AppAccess],

    // Paywall Discount, App Store.
    'com.fitnation.app.premium.discount10.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount10.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount20.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount20.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount30.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount30.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount50.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount50.yearly' => [Entitlement::AppAccess],

    // Paywall Discount, Google Play.
    'com.fitnation.app.premium.discount10.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount10.yearly:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount20.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount20.yearly:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount30.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount30.yearly:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount50.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.discount50.yearly:yearly' => [Entitlement::AppAccess],
];
