# Fit Nation

Fitness tracking platform: a Laravel backend serving a customer web app, a mobile app, and an admin panel. Customers belong to partners; training programs are generated from onboarding answers.

## Language

**Partner**:
A gym or business whose customers use Fit Nation under its branding, reached via its subdomain (e.g. `citygym.fitnation.mk`).
_Avoid_: gym (as a model name), tenant

**House Partner**:
Fit Nation itself, acting as the partner for everyone who joins without a gym. Identified by `HOUSE_PARTNER_ID`; cannot be deactivated.
_Avoid_: default partner, no-partner

**Gym Signup**:
A registration under any partner other than the House Partner. Gym Signups are auto-verified: the gym vouches for the customer, so no verification email is sent.
_Avoid_: partner registration (ambiguous — every registration has a partner)

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
