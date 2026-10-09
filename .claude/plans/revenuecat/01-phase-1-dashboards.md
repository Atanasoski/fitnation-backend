# Phase 1 — Dashboard Setup

> **No code in this phase.** This is manual setup of App Store Connect, Google Play Console, and RevenueCat.

## Prerequisites

- Apple Developer Program ($99/yr) — required, already in place since the app is live.
- Google Play Console ($25 one-time) — already in place.
- RevenueCat account — free tier covers up to $2.5K/mo tracked revenue.

> **Closed testing note:** All of this works fine while the app is still in closed testing. Add yourself as a **License Tester** (Play Console → Settings → License Testing) and use **Sandbox Testers** (App Store Connect → Users and Access → Sandbox Testers) so test purchases don't charge real money. Subscriptions can be created and configured before going to production; they're a separate Apple/Google review queue that runs in parallel.

---

## 1.1 App Store Connect

> **Before you start:** Get your **App-Specific Shared Secret** — RevenueCat needs this to validate Apple receipts.
> App Store Connect → Users and Access → Integrations → App-Specific Shared Secret → Generate.
> Copy it; you'll paste it into RevenueCat in step 1.3.

1. Go to your app → "In-App Purchases" → "Manage."
2. Create **Auto-Renewable Subscription Group** called `fitnation_premium`.
3. Inside that group, create two subscriptions:
   - Product ID: `com.fitnation.app.premium.monthly` — duration 1 month — price $4.99 USD.
   - Product ID: `com.fitnation.app.premium.yearly` — duration 1 year — price $39.99 USD.
4. For each subscription, configure **Introductory Offer**: Free Trial, 7 days.
5. Fill in localized display name + description for each (required for review).
6. Submit subscriptions for review (Apple reviews subs separately from app updates).

---

## 1.2 Google Play Console

1. Go to your app → "Monetize" → "Products" → "Subscriptions."
2. Create base plans:
   - Product ID: `com.fitnation.app.premium.monthly` — billing period: monthly — price $4.99 USD.
   - Product ID: `com.fitnation.app.premium.yearly` — billing period: yearly — price $39.99 USD.
3. For each, add a **Free trial offer**: 7 days, eligibility "new customers only."
4. Activate base plans.

---

## 1.3 RevenueCat Dashboard

1. Create RevenueCat account at https://app.revenuecat.com.
2. Create project "Fit Nation."
3. Add Apple app — paste App Store Connect shared secret + bundle ID.
4. Add Google Play app — upload service account JSON. To get it: Google Play Console → Setup → API access → Link to a Google Cloud project → Create service account → grant it "Financial data" + "Orders" permissions → download JSON key. Paste into RevenueCat.
5. Create **Entitlement** called `app_access`.
6. Create **Products** matching the IDs above (`com.fitnation.app.premium.monthly`, `com.fitnation.app.premium.yearly`) and attach both to the `app_access` entitlement.
7. Create an **Offering** called `default` with two **Packages**:
   - Package `$rc_monthly` → product `com.fitnation.app.premium.monthly`
   - Package `$rc_annual` → product `com.fitnation.app.premium.yearly`
8. Copy the **public SDK keys** (one per platform) — needed for mobile app (Phase 3).
9. Copy the **webhook secret** — needed for Laravel webhook verification (drop into `.env` as `REVENUECAT_WEBHOOK_SECRET`).
10. Set webhook URL to `https://<your-domain>/api/webhooks/revenuecat` (already built in Phase 2).

---

## Output of this phase

- Two product IDs live in both stores (`com.fitnation.app.premium.monthly`, `com.fitnation.app.premium.yearly`).
- RevenueCat entitlement + offering configured.
- SDK keys (iOS + Android) in hand.
- Webhook secret in hand → drop into Laravel `.env` as `REVENUECAT_WEBHOOK_SECRET`.

---

## Verification

- **App Store Connect:** products show "Ready to Submit" or "Approved."
- **Play Console:** subscriptions show "Active."
- **RevenueCat:** offering "default" shows both packages with correct prices.
- **End-to-end smoke:** send a test event from RC dashboard ("Send Test Event") → confirm a row appears in Laravel `webhook_calls` table.
