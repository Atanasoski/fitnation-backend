# Context

Glossary for the muscle-hustle domain. Terms only — no implementation detail.

## Measurement

### Unit System

A user's preference for how measurements are shown to them and how they submit
them: `metric` or `imperial`. It is a **presentation and input preference**, not
a storage format — the same underlying measurement is the same value regardless
of which unit system reads it. Every user has one; metric is the default.

### Canonical Units

The single storage format for every measurement: **kilograms** for weight,
**centimetres** for height. Nothing persisted is ever in pounds or inches. When
a measurement is described without a unit anywhere in the domain, it is in
canonical units.

See [ADR-0001](docs/adr/0001-convert-units-at-the-http-boundary.md) for where
conversion happens and why.

### Training Weight

The load on an exercise — what a user lifts. Displayed in imperial to the
nearest **5 lbs**, because plates come in fixed increments and a training weight
of "138.9 lbs" is not a thing anyone can load onto a bar.

Distinct from [Body Weight](#body-weight) despite both being weights; they round
differently and are never interchangeable.

### Body Weight

A user's own mass, recorded on their profile. Displayed in imperial to the
nearest **0.5 lb** — finer than a [Training Weight](#training-weight), because
body-weight change is tracked over time and 5 lb granularity would erase the
signal.

### Target Weight

The [Training Weight](#training-weight) a user is aiming for on an exercise. Two
kinds exist and they behave differently:

- **Template target weight** — stored on the template's exercise row, edited
  directly by the user, and read back as stored.
- **Session target weight** — recomputed on every read from the user's latest
  completed session. A stored value on a session exercise is an input to that
  calculation, never the answer.
