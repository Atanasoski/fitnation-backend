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

**Mesocycle**:
One generated Program block: identical accumulation weeks (same workouts every week, loads progressing) ending in a Deload Week. Exercise variety arrives between Mesocycles — each newly generated Program draws fresh exercises — never within one.
_Avoid_: cycle, block (unqualified)

**Deload Week**:
The final week of a Mesocycle: a scheduled low-volume recovery week. Every exercise drops to 2 sets; compounds at 3–4 reps, isolations keep their normal rep range. The Working Weight is unchanged. Sessions completed in a Deload Week are recovery, not performance: they never count as performance history.
_Avoid_: rest week, recovery week

**Working Weight**:
The weight progression currently prescribes for an exercise: recomputed on every read from the user's latest completed working (non-deload) session of that exercise, never stored as a target. A stored `target_weight` on a template or session row is only the starting estimate for a user with no history.
_Avoid_: current weight, last weight

**Prescription**:
The targets handed to a user for one exercise in one session: sets, rep range, rest, and Working Weight. A user's own history, once it exists, always outranks any freshly computed default.
_Avoid_: targets (unqualified), defaults

**Compound Movement**:
A multi-joint Exercise, classified by its movement pattern. Compounds fill a Blueprint's heavy early Slots and earn more sets, heavier starting weights, and lower-rep Prescriptions.
_Avoid_: big lift, main lift

**Isolation Movement**:
A single-joint Exercise, classified by its movement pattern. Fills a Blueprint's later Slots, with fewer sets and higher-rep Prescriptions. An isolation is never reclassified to borrow a compound's treatment.
_Avoid_: accessory, auxiliary

**Blueprint**:
The declared shape of one workout day type: an ordered list of Slots. Six exist — Push, Pull, Upper, Lower, Full Body, Arms. A day's composition is read off its Blueprint, never emergent from selection rules.
_Avoid_: template (collides with WorkoutTemplate), day plan

**Slot**:
One position in a Blueprint: a movement role (pattern, optionally angle) carrying a Tier, a set count, and a rep range. Filled by exactly one Exercise satisfying the role; a Slot that cannot fill even after the Relaxation Waterfall is skipped and its sets move to earlier Slots.
_Avoid_: position, exercise entry

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
An Exercise's curated baseline rest between sets. An input to the Prescription — scaled by the user's fitness goal — not the rest the user necessarily sees.
_Avoid_: rest (unqualified)
