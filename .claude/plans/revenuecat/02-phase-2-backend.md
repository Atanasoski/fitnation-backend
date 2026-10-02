# Phase 2 — Laravel Backend

All paths below are inside `/Users/kirilatanasoski/Desktop/Projects/muscle-hustle/`.

> **Status:** ✅ Code implemented. ⚠️ Test coverage from the Verification section below is **not yet written** — see 2.8.

---

## 2.1 Migrations

### `database/migrations/2026_05_19_020607_create_subscriptions_table.php`

```
subscriptions:  # one row per user, represents CURRENT state
  - id
  - user_id (FK → users, unique — one row per user)
  - partner_id (FK → partners, nullable, indexed)
      # The gym the user was at when they subscribed. Frozen at subscribe time,
      # NEVER updated when user.partner_id changes. Used for acquisition
      # reporting and future revenue share.
  - product_id (string, e.g. "com.fitnation.app.premium.monthly")
  - store (enum: app_store, play_store)
  - status (enum: active, cancelled, expired, billing_issue, paused)
      # Pure lifecycle state. NOTE: "cancelled" means the user cancelled but
      # may still have access until expires_at — see isInGracePeriod().
      # "paused" is Android-only (user paused — access ends at expires_at, resumes automatically).
  - period_type (enum: normal, trial, intro, promotional)
      # What kind of period this is. Matches RevenueCat webhook values.
  - price (decimal 10,2, nullable)
      # Price paid in the user's currency. Pulled from webhook for reporting.
  - currency (char 3, nullable)  # ISO 4217 currency code, e.g. "USD"
  - purchased_at (timestamp)
  - expires_at (timestamp, nullable)
  - cancelled_at (timestamp, nullable)  # set on CANCELLATION event
  - environment (enum: sandbox, production)
  - timestamps
  - INDEX (status, expires_at)  # for "expiring soon" queries
```

### `database/migrations/2026_05_19_020607_add_plan_to_partners_table.php`

```
partners:
  + plan (enum: free, sponsor — default 'free')
      # The gym's billing relationship with Fit Nation.
      # 'free'    = default, gym uses the platform free, members must subscribe individually.
      # 'sponsor' = gym pays Fit Nation, members get free app access.
      # Extensible: future tiers (e.g. 'enterprise', 'white_label') slot in here.
  + plan_expires_at (timestamp, nullable)
      # When the current plan ends. After this, gym effectively reverts to 'free'.
      # Null = no expiry (e.g. permanent free tier, or open-ended sponsor contract).
```

### `database/migrations/2026_05_19_020608_add_grace_period_ends_at_to_users_table.php`

```
users:
  + grace_period_ends_at (timestamp, nullable)
      # Bonus free access end date (launch grandfathering, customer comps, promos).
      # NOTE: Distinct from "billing grace period" (payment-retry window) which lives
      # on subscriptions if/when needed.
```

**Backfill (same migration):** sets `grace_period_ends_at = now() + 30 days` for all existing users (one-time launch grandfathering).

---

## 2.2 PHP Enums

**`app/Enums/SubscriptionStatus.php`** — cases: `Active`, `Cancelled`, `Expired`, `BillingIssue`, `Paused` (values: `'active'`, `'cancelled'`, `'expired'`, `'billing_issue'`, `'paused'`).

**`app/Enums/SubscriptionPeriodType.php`** — cases: `Normal`, `Trial`, `Intro`, `Promotional`.

**`app/Enums/SubscriptionStore.php`** — cases: `AppStore`, `PlayStore` (values: `'app_store'`, `'play_store'`).

**`app/Enums/PartnerPlan.php`** — cases: `Free`, `Sponsor` (values: `'free'`, `'sponsor'`).

**`app/Enums/Entitlement.php`** — backed string enum. Today only `AppAccess` (`'app_access'`). Grows over time (e.g. `PremiumNutrition`, `LiveClasses`).

---

## 2.3 Entitlements Config

**File:** `config/entitlements.php`

```php
use App\Enums\Entitlement;

return [
    'com.fitnation.app.premium.monthly' => [Entitlement::AppAccess],
    'com.fitnation.app.premium.yearly'  => [Entitlement::AppAccess],
    // Future:
    // 'com.fitnation.app.premium.nutrition' => [Entitlement::AppAccess, Entitlement::PremiumNutrition],
];
```

To add a new gated product/feature later, edit this file + add one enum case. No model code changes, no API contract change.

---

## 2.4 Models

### `app/Models/Subscription.php`

- `belongsTo(User::class)`
- `belongsTo(Partner::class, 'partner_id')` as `partner()` — the gym that brought this subscription.
- Casts: timestamps, enums (use `SubscriptionStatus`, `SubscriptionPeriodType`, `SubscriptionStore`), `price` as decimal.
- Scopes: `active()`, `expiringSoon()`, `acquiredVia(Partner $partner)`.
- Methods:
  - `isActive(): bool` — `(status == active OR isInGracePeriod()) && expires_at > now()`.
  - `isInGracePeriod(): bool` — `status == cancelled && expires_at > now()`. (User cancelled but still has time left.)
  - `isInTrial(): bool` — `period_type == trial && isActive()`.
  - `grantedEntitlements(): Collection` — looks up `product_id` in `config('entitlements')` and returns the entitlement set this product unlocks.

> **Note on audit log:** Raw webhook payloads are stored in Spatie's `webhook_calls` table (auto-managed). To query subscription history per user, filter `webhook_calls` by name and JSON payload — see 2.6.

### `app/Models/User.php` (updated)

- Added `hasOne(Subscription::class)` relation `subscription()`.
- Added `grace_period_ends_at` to casts as datetime.
- Added methods:
  - `entitlements(): Collection` — merges entitlements from subscription, gym sponsorship, and grace period; deduplicates.
  - `hasEntitlement(Entitlement $e): bool`
  - `hasAppAccess(): bool` — convenience wrapper around `hasEntitlement(Entitlement::AppAccess)`.

### `app/Models/Partner.php` (updated)

- Added `plan` to `$casts` as `PartnerPlan::class`.
- Added `plan_expires_at` to `$casts` as `datetime`.
- Added both to `$fillable`.
- Added method `isSponsoringMembers(): bool` — returns true when `plan == Sponsor` and `plan_expires_at` is null or future.

---

## 2.5 API — User Endpoint Extension

**File:** `app/Http/Resources/Api/UserResource.php`

Added:

```php
'entitlements' => $this->entitlements()->map(fn ($e) => $e->value)->all(),
'subscription' => [
    'status' => $this->subscription?->status?->value,
    'expires_at' => $this->subscription?->expires_at,
    'is_trial' => $this->subscription?->isInTrial() ?? false,
    'is_sponsored_by_gym' => $this->partner?->isSponsoringMembers() ?? false,
    'grace_period_ends_at' => $this->grace_period_ends_at,
],
```

Mobile app reads `entitlements` array and checks `entitlements.includes('app_access')` to decide whether to show the paywall. Future feature flags work the same way — no API contract change ever.

---

## 2.6 Webhook Handling (Spatie laravel-webhook-client)

We use Spatie's `laravel-webhook-client` package to handle all incoming webhooks generically — RevenueCat today, easily extensible to Stripe / Mailgun / etc. tomorrow. The package gives us:

- Generic `webhook_calls` table (auto-migration) — universal audit log for every webhook from any provider.
- Plug-and-play signature validators per provider.
- Async processor jobs per provider.
- Built-in retry handling, automatic 200 responses, request logging.

### Install (already done)

```bash
composer require spatie/laravel-webhook-client
php artisan vendor:publish --tag="webhook-client-migrations"
php artisan vendor:publish --tag="webhook-client-config"
php artisan migrate
```

### Config — `config/webhook-client.php`

```php
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
```

### Route — `routes/api.php`

```php
// Public webhooks (signature-verified)
Route::webhooks('webhooks/revenuecat', 'revenuecat')
    ->middleware('throttle:60,1');
```

### Signature Validator — `app/Webhooks/RevenueCat/RevenueCatSignatureValidator.php`

Reads `Authorization: Bearer <secret>` header, strips `Bearer `, compares with `hash_equals` (timing-safe) against `config->signingSecret`. Returns false if either is missing.

### Processor Job — `app/Webhooks/RevenueCat/ProcessRevenueCatWebhook.php`

Extends `Spatie\WebhookClient\Jobs\ProcessWebhookJob`. Job has `$tries = 5` cap so a permanently unmatchable user ID doesn't clog the queue.

Steps (in `handle()`):

1. Validate event payload is present.
2. **Sandbox guard:** skip sandbox events in production environment to avoid polluting real subscription data.
3. **TEST event short-circuit:** RevenueCat's "Send Test Event" dashboard button emits `type=TEST` with no `app_user_id`. Log and return 200 — used for end-to-end smoke testing the webhook URL.
4. Resolve user by `event.app_user_id`. **Throws** if not found (queue retries; webhook payload is safely stored in `webhook_calls`).
5. Dispatch by event type inside a DB transaction:
   - `INITIAL_PURCHASE` → create subscription row with `partner_id = user->partner_id` frozen at this moment.
   - `RENEWAL`, `PRODUCT_CHANGE`, `PRICE_CHANGE` → update existing row; do NOT touch `partner_id`.
   - `CANCELLATION` → `status=cancelled`, `cancelled_at=now()`. Access stays until `expires_at`.
   - `UNCANCELLATION`, `SUBSCRIPTION_RESUMED` → `status=active`, clear `cancelled_at`.
   - `EXPIRATION` → `status=expired`.
   - `BILLING_ISSUE` → `status=billing_issue`.
   - `SUBSCRIPTION_PAUSED` (Android) → `status=paused`.
   - `TRANSFER` → **throws RuntimeException** for manual review (silently completing would mark subscription ownership as resolved when it isn't).
   - Unknown event types → log and return (don't fail).

> **Note:** RevenueCat does NOT send separate `TRIAL_STARTED` / `TRIAL_CONVERTED` events. Trials are signaled via `period_type=TRIAL` on the `INITIAL_PURCHASE` event, and the conversion to paid is signaled via a `RENEWAL` event with `period_type=NORMAL`.

### Env

`REVENUECAT_WEBHOOK_SECRET=` added to `.env` and `.env.example` — value filled in after Phase 1 dashboard setup.

### Adding new webhook providers later

To add Stripe (for example):
1. Add a `stripe` entry to `config/webhook-client.php`.
2. Implement `StripeSignatureValidator` + `ProcessStripeWebhook`.
3. Add `Route::webhooks('webhooks/stripe', 'stripe');` to `routes/api.php`.

No changes to existing RevenueCat code. No new tables.

---

## 2.7 Server-side Access Gate — `RequiresSubscription` Middleware

**File:** `app/Http/Middleware/RequiresSubscription.php`

Defense-in-depth on top of the client-side paywall. The mobile app reads the `entitlements` array on `GET /api/user` and renders the paywall when `app_access` is missing — but a malicious or stale client could still hit the rest of the API directly. This middleware enforces the same gate server-side.

```php
public function handle(Request $request, Closure $next): Response
{
    if (! $request->user()?->hasEntitlement(Entitlement::AppAccess)) {
        return response()->json(['message' => 'Subscription required.'], 403);
    }

    return $next($request);
}
```

### Route wiring — `routes/api.php`

Endpoints split into three tiers inside the `auth:sanctum` group:

1. **Always allowed** (no entitlement check): `POST /logout`, `POST /email/verification-notification`.
2. **Pre-paywall** (no entitlement check, but auth required): `GET /api/user`, `DELETE /api/user`, `POST /onboarding/complete` — the mobile app calls these to decide whether to show the paywall and to finish onboarding before purchase.
3. **Gated** (everything else — fitness metrics, profile, exercises, plans, programs, routines, workout templates, workout sessions, planner, etc.) wrapped in `Route::middleware(RequiresSubscription::class)`.

### Notes
- Returns plain JSON `{"message":"Subscription required."}` with status 403. Mobile app should treat 403 from a gated endpoint as a hard signal to route to the paywall (cache may be stale).
- Grace period, gym-sponsored access, and active subscription all flow through `User::hasEntitlement()`, so no per-channel logic lives here.
- During sandbox testing on devices whose webhook hasn't landed yet, the user will get 403s on gated endpoints. Either give the test user a future `grace_period_ends_at`, or flip their gym's `plan` to `sponsor`, before testing protected flows.

---

## Verification

### Smoke test (confirmed working)

User #1 (in Fit Nation gym) returns:
- `entitlements: ["app_access"]`
- `hasAppAccess: true`
- Via the 30-day grace period backfill.

## 2.8 Pending Test Coverage

> **Status:** ⚠️ None of the tests below are written yet. Must land before Phase 3 sandbox testing — these are how we know the webhook handler is correct before real money flows through it.

### Unit tests to write (`tests/Unit/`)
- `User::factory()->create()->entitlements()` returns empty collection (no sub, no grace, no sponsorship).
- `User::factory()->create()->hasAppAccess()` returns `false` in same state.
- Existing user with `grace_period_ends_at > now()` → `entitlements` contains `AppAccess`.
- User whose partner has `plan = sponsor` (no expiry) → `entitlements` contains `AppAccess`.
- User whose partner has `plan = sponsor` but `plan_expires_at` in the past → `entitlements` is empty.
- User whose partner has `plan = free` and no subscription → `entitlements` is empty.
- User with active subscription on `com.fitnation.app.premium.monthly` → `entitlements` contains `AppAccess` (via product → entitlement map).
- `entitlements()` deduplicates when multiple sources grant the same entitlement.
- Subscription with `status=cancelled` and `expires_at > now()` — `isInGracePeriod()` and `isActive()` both true.
- Subscription with `status=cancelled` and `expires_at < now()` — both false.
- Subscription with `period_type=trial` and `status=active` — `isInTrial()` true.

### Integration tests to write (`tests/Feature/`)
- POST to `/api/webhooks/revenuecat` with sample `INITIAL_PURCHASE` payload + correct `Authorization: Bearer <secret>` header → creates `subscriptions` row, `webhook_calls` row.
- Same POST with wrong/missing auth header → rejected by Spatie before reaching the job, no rows created.
- Sandbox event in production environment → logged + skipped, no subscription created.
- `TEST` event → 200 OK, no user lookup attempted, no subscription created.
- Same `INITIAL_PURCHASE` payload twice → idempotent (Spatie handles).
- `CANCELLATION` event marks subscription cancelled but `hasAppAccess()` still true until `expires_at`.
- `TRANSFER` event throws RuntimeException (manual review).
- Unknown event type → 200 OK, no subscription change.
- `RequiresSubscription` middleware: authenticated user with `hasAppAccess() === false` → 403 on a gated route (e.g. `GET /api/exercises`).
- `RequiresSubscription` middleware: authenticated user with `hasAppAccess() === true` (via active sub OR grace OR sponsor) → 200 on the same route.
- `RequiresSubscription` middleware: `GET /api/user` still returns 200 for a user without `app_access` (pre-paywall route, must stay reachable).
