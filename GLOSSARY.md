# Fit Nation

Fitness tracking platform: a Laravel backend serving a customer web app, a mobile app, and an admin panel. Customers belong to partners; training programs are generated from onboarding answers.

## Language

**Partner**:
A gym or business whose customers use Fit Nation under its branding. Customers arrive via its subdomain (a Gym Signup) or through a Gym Join from the House Partner.
_Avoid_: gym (as a model name), tenant

**House Partner**:
Fit Nation itself, acting as the partner for everyone who joins without a gym. Identified server-side by configuration; API clients read the `is_house` flag on partner payloads, never an id. Cannot be deactivated.
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
A fixed tier — 10, 20, 30 or 50% off the subscription — that a super admin gives a Partner; its customers see the discounted prices on the paywall. Each tier is its own monthly and yearly products in both stores (`com.fitnation.app.premium.discount<percent>.<period>`; on the App Store in its own subscription group, because iOS lets a subscriber switch to any plan in their group), grouped in the RevenueCat offering `discount-<percent>`, which the app shows instead of the default one. A deactivated Partner discounts no one. The stores never reprice a running subscription: a customer already paying full price keeps that price until they switch plans, and one who leaves the Partner keeps the discount until they cancel.
_Avoid_: coupon, promo code, offer code (store features this does not use)

**Onboarding**:
The post-registration questionnaire (profile, goal, training setup) that ends with the welcome program being generated. Lives only in the mobile app; the web app sends non-onboarded users to the app download screen instead.

**App Download Screen**:
The web page a registered, not-yet-onboarded customer lands on: store badges plus a QR code pointing at the `/get` smart store redirect.

**Exercise**:
A catalogued movement (muscle groups, category, equipment type), maintained centrally and offered through Partner Libraries, optionally with partner-specific media/description overrides.

**Partner Library**:
The subset of the Exercise catalogue a Partner offers its customers; workouts are generated only from it. A new Partner starts with the whole catalogue and curates from there; the House Partner's library is curated by Fit Nation. Taking an Exercise out of the library stops it being offered or generated, but programs customers already have keep it. An Exercise on one of the Partner's Stations cannot be taken out until it is off every Station.
_Avoid_: partner catalogue, partner exercises

**Partner Override**:
A Partner's own description, image or video for an Exercise in its Partner Library, shown to its customers instead of the catalogue's. Each of the three overrides independently; one left blank shows the catalogue version. Taking the Exercise out of the Partner Library discards its override.
_Avoid_: partner media, custom media

**EquipmentType**:
A classification of what an Exercise needs to be performed (Barbell, Cable, Machine, …). Catalogue metadata — never a physical object on a gym floor.
_Avoid_: equipment (ambiguous), machine

**Station**:
A physical spot on a Partner's gym floor (e.g. "Cable Crossover") carrying a printed QR code and a curated, ordered list of Exercises that can be performed there, always drawn from the Partner's Library. Identical machines at the same gym share one Station. The only thing a QR sticker ever points at.
_Avoid_: equipment, machine, QR target

**Short code**:
A Station's immutable public identifier, generated once and never regenerated, because printed stickers cannot be cheaply replaced.

**Scan**:
A logged visit to a Station's public page via its QR code: which Station, when, which app benefit was shown, and whether the download CTA was clicked.

**Mesocycle**:
One generated Program block: identical accumulation weeks (same workouts every week, loads progressing) ending in a Deload Week. Exercise variety arrives between Mesocycles — each newly generated Program draws fresh exercises — never within one.
_Avoid_: cycle, block (unqualified)

**Deload Week**:
The final week of a Mesocycle: a scheduled low-volume recovery week. Every exercise drops to 2 sets; compound Slots at 3–4 reps, isolation Slots keep their normal rep range. The Working Weight holds: it is neither raised nor lowered during a Deload Week. Sessions completed in a Deload Week are recovery, not performance: they never count as performance history.
_Avoid_: rest week, recovery week

**Working Weight**:
The weight progression currently prescribes for an exercise: recomputed on every read from the user's latest completed working (non-deload) session of that exercise, never stored as a target. It rises one equipment step once every set of that session topped its Logged Range. A stored `target_weight` on a template or session row is only the starting estimate for a user with no history.
_Avoid_: current weight, last weight

**Logged Range**:
The rep range a set was prescribed at the moment it was logged. Progression judges a performance against its Logged Range, never against the range prescribed today.
_Avoid_: target range, current range

**Prescription**:
The targets handed to a user for one exercise in one session: sets, rep range, rest, and Working Weight. A user's own history, once it exists, always outranks any freshly computed default.
_Avoid_: targets (unqualified), defaults

**Compound Movement**:
A multi-joint Exercise, classified by its movement pattern. Compounds fill a Blueprint's heavy early Slots and start heavier; outside a generated day they also take lower-rep Prescriptions.
_Avoid_: big lift, main lift

**Isolation Movement**:
A single-joint Exercise, classified by its movement pattern. Fills a Blueprint's later Slots, with fewer sets and higher-rep Prescriptions. An isolation is never reclassified to borrow a compound's treatment.
_Avoid_: accessory, auxiliary

**Blueprint**:
The declared shape of one workout day type: an ordered list of Slots. Six exist — Push, Pull, Upper, Lower, Full Body, Arms. A day's composition is read off its Blueprint, never emergent from selection rules. A fragment-composed day reuses the Blueprint shape (its Slots come from Fragments, tier-filtered and budget-trimmed) without being one of the six.
_Avoid_: template (collides with WorkoutTemplate), day plan

**Slot**:
One position in a Blueprint: a movement role (pattern, optionally angle) carrying a Tier, a set count, and a rep range. Each Slot is a compound or an isolation Slot, and that class, not the class of the Exercise filling it, decides a generated row's Prescription and deload. Filled by exactly one Exercise satisfying the role; a Slot that cannot fill even after the Relaxation Waterfall is skipped and its sets move to earlier Slots.
_Avoid_: position, exercise entry

**SlotRole**:
A Slot's movement role: the set of movement patterns that satisfy it, each optionally narrowed to specific angles (e.g. `PRESS` at `FLAT|HORIZONTAL`, or `PUSHUP` at any angle). An Exercise fills a Slot by matching any one of its roles.
_Avoid_: pattern filter, role (unqualified)

**Tier**:
A Slot's priority class deciding which session lengths include it: tier-1 Slots alone make an Express session, tiers 1–2 a Standard session, all tiers an Extended session. Session length is chosen from a fixed set of durations; each maps to exactly one of the three.
_Avoid_: priority (collides with selection priority)

**Relaxation Waterfall**:
The ordered loosening of an empty Slot's role until exercises match: drop the angle; drop the pattern while keeping region and compound/isolation class; fall back to bodyweight equivalents; finally skip the Slot.
_Avoid_: fallback (unqualified)

**Fragment**:
A target region's short, ordered Slot list, used to compose a day for a custom region combination that matches no Blueprint.
_Avoid_: partial blueprint

**Default Rest**:
An Exercise's curated baseline rest between sets; every Exercise has one. An input to the Prescription — scaled by the user's fitness goal — not the rest the user necessarily sees.
_Avoid_: rest (unqualified)
