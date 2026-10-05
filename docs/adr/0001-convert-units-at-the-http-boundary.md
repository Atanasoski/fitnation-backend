# Convert units at the HTTP boundary

Users pick a Unit System (`metric` or `imperial`), but we store every
measurement in Canonical Units — kilograms and centimetres, always. Conversion
happens **strictly at the HTTP boundary**: API Resources convert on the way out,
FormRequests convert (in `prepareForValidation()`) on the way in. Everything
inside that boundary — Eloquent models, `FitnessMetricsService`,
`ProgressionCalculatorService`, every query and aggregate — only ever sees kg and
cm, and has no idea unit systems exist.

## Considered Options

- **Convert in the model layer** (accessors/mutators on `UserProfile`, `SetLog`,
  etc.). Rejected: it makes the value of `$setLog->weight` depend on who is
  logged in, which silently corrupts every aggregate, comparison, and
  progression calculation that reads it outside a request context — queue jobs
  and seeders most of all.
- **Convert on the client.** Rejected: it puts the conversion constants in three
  codebases (web, mobile, and any future client) and guarantees they drift. The
  front-end deliberately holds no conversion math at all — it reads
  `unit_system` purely as a display label.
- **Store both units.** Rejected: two columns that can disagree.

## Consequences

Validation bounds stay written in kg/cm (`weight` min 1 / max 500) and apply to
the *converted* value, because `prepareForValidation()` runs first. This is why
an imperial user submitting 1200 lbs gets the existing kg-based error.

Every new write endpoint that accepts a weight or height **must** convert, or it
silently stores pounds in a kilogram column. Read and write must be added
together: converting only the read path produces a display/save asymmetry that
corrupts data with no error.

Because that failure is silent, the pairing is enforced rather than trusted. A
Measured Field's kind is declared once in `MeasuredFields`, and both paths look
it up rather than being told it — so a request and a resource cannot disagree
about a column, and an unregistered column throws instead of passing through
raw. `tests/Feature/MeasurementInvariantsTest.php` then holds three guards: every
measured column in the schema is registered, every API request accepting one
converts it, and display round trips reach a fixed point.

That last property is the one to preserve if the others are ever reworked.
Identity across a round trip is *not* the invariant — display rounding
legitimately moves 137 lbs to 135 — but a value that keeps moving is always a
bug, covering both unit asymmetry and slow per-save drift.

Admin and partner Blade tooling is deliberately excluded and stays metric — it
is staff-facing, and staff read the canonical numbers — with one exception
(2026-10-05, issue 022): the super-admin **User page** shows a person's height,
weight and Best sets in *their* Unit System, because a super admin reading it is
usually answering that person's support question in the numbers they see in the
app. It is read-only and converts through `MeasuredFields`, the same seam as the
API; nothing on it writes a measurement, so the write half of this ADR does not
apply.

A second exception (2026-10-05, issue 023): the **plan outline**
(`/users/{user}/plans`) shows and takes a row's target weight in the *plan
owner's* Unit System, so staff prescribe what the person will see. It writes,
so both halves apply: the row requests convert through `ConvertsIncomingUnits`
with the owner's system (`Plan::ownerUnitSystem()`, not the signed-in staff
member's), and the page converts for display through `MeasuredFields`. Library
plans have no owner and stay metric.
