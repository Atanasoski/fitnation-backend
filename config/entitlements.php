<?php

use App\Enums\Entitlement;

// Product ID (as RevenueCat sends it in the webhook) → the entitlements it grants.
//
// Google Play products carry their base plan: RevenueCat reports a Play
// subscription as `<product id>:<base plan id>`, and the base plans are named
// `monthly` and `yearly` in Play Console / RevenueCat. App Store products are
// the bare ID. Both spellings grant the same access.
return [
    'com.fitnation.app.premium.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.monthly:monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly:yearly' => [Entitlement::AppAccess],
];
