# Phase 3 — Mobile App (Expo, separate repo)

All paths below are inside `/Users/kirilatanasoski/Desktop/Projects/fitnation/apps/mobile/`.

> **Prerequisites:** Phase 2 backend deployed AND Phase 1 dashboards configured.
> - Backend exposes `entitlements` array on `GET /api/user`. ✅ Already shipped.
> - RevenueCat offerings + entitlements are live in the RC dashboard.

> **Closed testing note:** This work is safe to do during Google Play closed testing. License Testers (Android) and Sandbox Testers (iOS) can complete the purchase flow without real charges; webhooks fire identically to production so the backend gets full end-to-end testing.

---

## 3.1 Install Dependencies

```bash
pnpm add react-native-purchases
npx expo install
```

> `react-native-purchases-ui` (RevenueCat's prebuilt Paywall UI) is intentionally excluded — we're building a custom paywall in 3.4 for full design control. Add it back only if you decide to switch to RC's prebuilt UI later.

Add to `apps/mobile/app.json` `plugins` array:

```json
["react-native-purchases", { "automaticAppleSearchAdsAttributionCollection": false }]
```

Run `npx expo prebuild` if using bare workflow, or just rebuild the dev client.

---

## 3.2 Initialize RevenueCat

**New:** `apps/mobile/src/lib/revenuecat.ts`

```ts
import Purchases, { LOG_LEVEL } from 'react-native-purchases';
import { Platform } from 'react-native';

export const configureRevenueCat = () => {
  Purchases.setLogLevel(__DEV__ ? LOG_LEVEL.DEBUG : LOG_LEVEL.WARN);
  Purchases.configure({
    apiKey: Platform.OS === 'ios'
      ? process.env.EXPO_PUBLIC_RC_API_KEY_IOS!
      : process.env.EXPO_PUBLIC_RC_API_KEY_ANDROID!,
  });
};

export const identifyRevenueCatUser = async (userId: string) => {
  await Purchases.logIn(userId);
};

export const logOutRevenueCat = async () => {
  await Purchases.logOut();
};
```

**Lifecycle hooks:**
- Call `configureRevenueCat()` at app entry (root `_layout.tsx` or `App.tsx`).
- Call `identifyRevenueCatUser(String(user.id))` right after successful login — pass the numeric DB ID as a **string**. This is what RevenueCat sends as `event.app_user_id` in webhooks, and the backend resolves it via `User::find($app_user_id)`. A mismatch here is the #1 cause of "unknown user" webhook failures.
- Call `logOutRevenueCat()` on logout.

---

## 3.3 Entitlements Hook & Access Gate

**New:** `apps/mobile/src/hooks/useEntitlements.ts`

- Pulls `entitlements` array + `subscription.*` from `GET /api/user` via TanStack Query (already wired up).
- Returns `{ entitlements: string[], has: (e: string) => boolean, subscription, isLoading }`.
- Define an `Entitlement` const/union on the mobile side mirroring the backend enum, for type safety:

```ts
export const Entitlement = { AppAccess: 'app_access' } as const;
export type Entitlement = (typeof Entitlement)[keyof typeof Entitlement];
```

**Navigation gate:**
- Wrap protected navigation stack with a gate component.
- If `!has(Entitlement.AppAccess)`, redirect to `/paywall`.
- Tomorrow's premium features call `has(Entitlement.PremiumNutrition)` etc. — same hook, no refactor.

---

## 3.4 Paywall Screen

**New:** `apps/mobile/src/screens/Paywall.tsx`

```ts
import Purchases, { PurchasesOffering, PurchasesPackage } from 'react-native-purchases';

// On mount:
const offerings = await Purchases.getOfferings();
const current = offerings.current; // the "default" offering from RC dashboard
if (!current) {
  // RC not configured yet, or network failure — show error state, never block the user
  return;
}
// Render current.availablePackages — typically [monthly, annual]

// On "Subscribe" tap:
const { customerInfo } = await Purchases.purchasePackage(selectedPackage);
if (customerInfo.entitlements.active['app_access']) {
  // Refetch GET /api/user, navigate into app
}

// On "Restore Purchases" tap (required by Apple):
const restored = await Purchases.restorePurchases();
if (restored.entitlements.active['app_access']) {
  // Refetch GET /api/user so the backend subscription row syncs
  // (same TanStack Query invalidation as post-purchase)
}
```

### UI requirements

- Two plan cards (monthly / yearly) with localized `priceString` from RC.
- Highlight yearly as "best value" (compute % savings from prices).
- "Start 7-day free trial" CTA copy (since intro offer is active).
- "Restore Purchases" link in footer.
- "Terms of Use" + "Privacy Policy" links (Apple requires both visible on paywall).
- Loading + error states.

---

## 3.5 Webhook-driven Sync

The mobile app does **NOT** update the backend directly after purchase. RevenueCat's webhook does that (Phase 2.6). After purchase succeeds on-device, the app:

1. Calls `Purchases.getCustomerInfo()` to confirm entitlement is active client-side.
2. **Refetches `GET /api/user`** (invalidate the TanStack Query) — backend `entitlements` will include `app_access` once the webhook lands (usually <2s).
3. Navigates into the app.

If the user navigates quickly and the webhook hasn't landed yet, the local RC entitlement check still grants access — so UX is instant.

> **Offline behaviour:** RevenueCat caches entitlements locally. When the device is offline, `getCustomerInfo()` returns the last cached state — do not treat an offline result as "no entitlement." Always fall back to the cached value rather than blocking the user.

---

## Verification

### Sandbox testing
- Run on iOS sandbox account (sandbox tester via App Store Connect → Users and Access → Sandbox Testers).
- Paywall loads with both plans + localized prices.
- Tap subscribe → native Apple sheet appears → confirm with sandbox account → entitlement activates → user lands in app.
- Check Laravel DB: row in `subscriptions` with `period_type=trial`, `status=active`.
- `GET /api/user` returns `entitlements: ["app_access"]`.
- Restore Purchases: uninstall + reinstall app, log in, tap Restore → access restored.
- Repeat on Android with a Play Console license tester account.
- Gym-sponsored bypass: set `partners.plan = 'sponsor'` for the test user's gym, log in fresh → paywall is skipped.

### End-to-end smoke
1. New user signs up → 7-day trial starts on first subscribe → access granted.
2. Trial expires (use sandbox accelerated time) → `EXPIRATION` webhook fires → backend marks expired → next `GET /api/user` returns `entitlements: []` → app routes to paywall.
3. Existing user (created before launch) logs in → `grace_period_ends_at` in future → has access without paying → after 30 days → loses access → paywall.
4. Gym attribution: Alice subscribes while at Gym X (`subscriptions.partner_id = X`). Update `users.partner_id` to Gym Y. Confirm `subscriptions.partner_id` is still X. Query "subscriptions acquired via Gym X" returns Alice's row.

---

## Phase 4 — Web (Deferred)

Web users post-launch must subscribe via mobile (or be gym-sponsored). Stripe-for-web is a later iteration once mobile is stable.
