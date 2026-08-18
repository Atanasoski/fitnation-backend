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
corrupts data with no error. Use the `ConvertsIncomingUnits` trait on the
request rather than hand-rolling it.

Admin and partner Blade tooling is deliberately excluded and stays metric — it
is staff-facing, and staff read the canonical numbers.
