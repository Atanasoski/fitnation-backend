<?php

use App\Enums\Entitlement;

// Product ID (as RevenueCat reports it) → the entitlements it grants.
//
// RevenueCat spells a Google Play subscription two ways: the webhook sends
// `<product id>:<base plan id>`, while the REST v1 subscriber that Subscription
// Sync reads gives the bare product id (the base plan apart, in
// product_plan_identifier). The base plans are named `monthly` and `yearly` in
// Play Console / RevenueCat. App Store products are the bare ID. Every
// spelling grants the same access: a missing one leaves a Play subscriber
// Subscribed with no access after a sync.
//
// Each Paywall Discount tier (PaywallDiscountTier) sells its own monthly and
// yearly product, with the same access as the standard products. App Store:
// `com.fitnation.app.premium.discount<percent>.<period>`, every tier in its own
// subscription group (iOS lets a subscriber switch to any plan in their group
// from Settings, so a discount in the standard group would be open to all).
// Google Play: `com.fitnation.app.discount<percent>.<period>`, because Play caps
// product IDs at 40 characters.
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

    // Paywall Discount, Google Play: as the webhook sends them…
    'com.fitnation.app.discount10.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount10.yearly:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount20.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount20.yearly:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount30.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount30.yearly:yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount50.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount50.yearly:yearly' => [Entitlement::AppAccess],

    // …and as Subscription Sync reads them. (The standard Play products need no
    // bare entries of their own: their bare IDs are the App Store IDs above.)
    'com.fitnation.app.discount10.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount10.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount20.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount20.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount30.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount30.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount50.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.discount50.yearly' => [Entitlement::AppAccess],
];
