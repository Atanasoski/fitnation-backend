# Fit Nation — RevenueCat Subscription: Overview

## Context

Fit Nation is shifting to a B2B2C model: gyms are acquisition channels, and the end customer paying Fit Nation is the individual user. Two access paths:

1. **Self-pay** — user buys a Fit Nation subscription via in-app purchase (Apple/Google IAP), orchestrated by RevenueCat.
2. **Gym-sponsored** — if a user's gym has `plan = sponsor` (paying Fit Nation), that user gets free app access (paywall bypassed entirely).

The previous gym-managed manual subscription code (on `feat/dashboard`) is being abandoned. This work starts fresh on `feat/revenuecat`, which is currently clean.

**Repos involved:**
- Laravel backend (API + admin dashboard): `/Users/kirilatanasoski/Desktop/Projects/muscle-hustle/`
- Mobile app (Expo SDK 54 + RN 0.81): `/Users/kirilatanasoski/Desktop/Projects/fitnation/` (separate repo)
- Web app (Ionic React): same fitnation monorepo, **deferred for later**

---

## Decisions

| Topic | Decision |
|---|---|
| IAP provider | RevenueCat (handles Apple StoreKit + Google Play Billing) |
| Plans | Monthly + Yearly |
| Pricing | $4.99/mo and $39.99/yr (changeable anytime via App Store Connect / Play Console) |
| Trial | 7-day free trial (configured as Apple/Google introductory offer) |
| Paywall model | Hard paywall after trial — must subscribe or be gym-sponsored to continue (changeable later — pure frontend logic) |
| Existing users | 30-day grace period from launch date, then paywall |
| Default gym | "Fit Nation" partner (slug `fit-nation`, exists in `PartnerSeeder.php`) catches users who leave their gym |
| Entitlement name | `app_access` (single entitlement, both plans grant it) |
| Gym attribution | Record `partner_id` on each subscription, frozen at subscribe time. Survives gym switches. Enables future revenue share / acquisition reporting. |
| Access model | **Entitlements array** (not binary boolean). Future feature gates are additive — no API contract changes ever. |
| Webhook handling | **Spatie laravel-webhook-client** package. Generic `webhook_calls` audit table. Easily extensible to Stripe / other providers later. |
| Account deletion + RC | If a user deletes their account (`DELETE /api/user`), their RevenueCat subscription keeps firing webhooks. `User::find()` skips soft-deleted records → job throws → retries 5× → lands in failed jobs. Known trade-off, accepted noise. No data corruption. |

---

## Execution Order

1. **Phase 2 (Backend)** — start here. Independent of dashboards; testable with fake webhook payloads. See `02-phase-2-backend.md`.
2. **Phase 1 (Dashboards)** — done in parallel (tomorrow). See `01-phase-1-dashboards.md`.
3. **Phase 3 (Mobile)** — after backend + dashboards are both ready. See `03-phase-3-mobile.md`.
4. **Phase 4 (Web)** — deferred indefinitely. Mobile-first.

---

## Critical Files Reference

### Existing files to read before implementing (backend)
- `app/Models/User.php` — current user model, partner relation
- `app/Models/Partner.php` — partner/gym model
- `app/Http/Controllers/Api/UserController.php` — current user endpoint
- `app/Http/Resources/Api/UserResource.php` — API serialization
- `routes/api.php` — route definitions, `auth:sanctum` group
- `database/seeders/PartnerSeeder.php` — confirms Fit Nation partner exists with slug `fit-nation`

### New files to create (backend)
- `database/migrations/*_create_subscriptions_table.php`
- `database/migrations/*_add_plan_to_partners_table.php`
- `database/migrations/*_add_grace_period_ends_at_to_users_table.php`
- `app/Models/Subscription.php`
- `app/Enums/SubscriptionStatus.php`
- `app/Enums/SubscriptionPeriodType.php`
- `app/Enums/SubscriptionStore.php`
- `app/Enums/PartnerPlan.php`
- `app/Enums/Entitlement.php`
- `config/entitlements.php`
- `app/Webhooks/RevenueCat/RevenueCatSignatureValidator.php`
- `app/Webhooks/RevenueCat/ProcessRevenueCatWebhook.php`
- `app/Http/Middleware/RequiresSubscription.php`
- (Spatie auto-publishes: `config/webhook-client.php`, `*_create_webhook_calls_table.php`)

### New files to create (mobile, separate repo)
- `apps/mobile/src/lib/revenuecat.ts`
- `apps/mobile/src/hooks/useEntitlements.ts`
- `apps/mobile/src/screens/Paywall.tsx`
- Route/navigation gate component
