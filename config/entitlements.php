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
// yearly product, named after the standard one plus `.d<percent>`. They grant
// the same access as the standard products.
return [
    'com.fitnation.app.premium.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly:yearly' => [Entitlement::AppAccess],

    // Paywall Discount, App Store.
    'com.fitnation.app.premium.monthly.d10' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly.d20' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly.d30' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly.d50' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d10' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d20' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d30' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d50' => [Entitlement::AppAccess],

    // Paywall Discount, Google Play.
    'com.fitnation.app.premium.monthly.d10:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly.d20:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly.d30:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly.d50:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d10:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d20:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d30:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly.d50:yearly' => [Entitlement::AppAccess],
];
