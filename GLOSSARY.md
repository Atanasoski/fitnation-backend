# Fit Nation

Fitness tracking platform: a Laravel backend serving a customer web app, a mobile app, and an admin panel. Customers belong to partners; training programs are generated from onboarding answers.

## Language

**Partner**:
A gym or business whose customers use Fit Nation under its branding. Customers arrive via its subdomain (a Gym Signup) or through a Gym Join from the House Partner.
_Avoid_: gym (as a model name), tenant

**House Partner**:
Fit Nation itself, acting as the partner for everyone who joins without a gym. Identified by `HOUSE_PARTNER_ID`; cannot be deactivated.
_Avoid_: default partner, no-partner

**Gym Signup**:
A registration under any partner other than the House Partner. Gym Signups are auto-verified: the gym vouches for the customer, so no verification email is sent. Distinct from a Gym Join, where the customer registered under the House Partner first.
_Avoid_: partner registration (ambiguous — every registration has a partner)

**Gym Join**:
The one-way move of a customer from the House Partner to a Partner, accepted from a Join Prompt. Verification is untouched: the customer keeps the verified email from their House registration. It cannot be undone or redirected to another Partner in the app; only staff can reassign a customer.
_Avoid_: switch, transfer

**Join Prompt**:
The offer to join a nearby Partner, shown to an eligible House Partner customer whose location falls inside one of that Partner's Gym Locations — between registration and Onboarding, and whenever the app comes to the foreground thereafter. Eligible means: on the House Partner, no active subscription, not staff. Declining ("Not interested") permanently silences the prompt for that Partner; snoozing ("Maybe later", or dismissing without choosing) silences it for 24 hours, indefinitely repeatable.

**Gym Location**:
A geographic point with a radius marking where a Partner's gym is; a Partner may have several. A customer "near" a Partner is one inside any of its Gym Locations. Partners only match when the Join Prompt is enabled for them.

**Paywall Discount**:
A fixed tier — 10, 20, 30 or 50% off the subscription — that a super admin gives a Partner; its customers see the discounted prices on the paywall. Each tier is its own monthly and yearly products in both stores, grouped in the RevenueCat offering `discount-<percent>`, which the app shows instead of the default one. A deactivated Partner discounts no one. The stores never reprice a running subscription: a customer already paying full price keeps that price until they switch plans, and one who leaves the Partner keeps the discount until they cancel.
_Avoid_: coupon, promo code, offer code (store features this does not use)

**Onboarding**:
The post-registration questionnaire (profile, goal, training setup) that ends with the welcome program being generated. Lives only in the mobile app; the web app sends non-onboarded users to the app download screen instead.

**App Download Screen**:
The web page a registered, not-yet-onboarded customer lands on: store badges plus a QR code pointing at the `/get` smart store redirect.

**Exercise**:
A catalogued movement (muscle groups, category, equipment type), maintained centrally and offered to partners, optionally with partner-specific media/description overrides.

**EquipmentType**:
A classification of what an Exercise needs to be performed (Barbell, Cable, Machine, …). Catalogue metadata — never a physical object on a gym floor.
_Avoid_: equipment (ambiguous), machine

**Station**:
A physical spot on a Partner's gym floor (e.g. "Cable Crossover") carrying a printed QR code and a curated, ordered list of Exercises that can be performed there. Identical machines at the same gym share one Station. The only thing a QR sticker ever points at.
_Avoid_: equipment, machine, QR target

**Short code**:
A Station's immutable public identifier, generated once and never regenerated, because printed stickers cannot be cheaply replaced.

**Scan**:
A logged visit to a Station's public page via its QR code: which Station, when, which app benefit was shown, and whether the download CTA was clicked.
