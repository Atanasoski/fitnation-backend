# Context

Glossary for the muscle-hustle domain. Terms only — no implementation detail.

## Workout Sessions

### Session Detail

A workout session resolved for the athlete performing it: the exercises it
holds, the sets logged against each, what the athlete did the last time they
trained that exercise, and what they are aiming for now.

Distinct from the session record itself, which holds only when the session
happened and how it went. A Session Detail is **derived** — asking for the same
session twice can legitimately give different answers, because an athlete's
history moves underneath it.

_Avoid_: session payload, session response.

### Session Exercise Detail

One exercise within a [Session Detail](#session-detail): the exercise, the
targets the athlete is working to, the sets they have logged against it, what
they did last time, and whether they are done with it.

The unit an athlete actually interacts with mid-workout — they work through a
session one Session Exercise Detail at a time. An exercise is **done** when the
number of sets logged against it reaches its target sets.

A session can carry the same exercise on more than one Session Exercise Detail
— a top set early and back-off sets later, or a superset repeat — so an
exercise does not identify one of these; the row does.

## Measurement

### Unit System

A user's preference for how measurements are shown to them and how they submit
them: `metric` or `imperial`. It is a **presentation and input preference**, not
a storage format — the same underlying measurement is the same value regardless
of which unit system reads it. Every user has one; metric is the default.

### Measured Field

A database column that holds a measurement, declared once against its
[Measurement Kind](#measurement-kind). A field's kind is a domain fact, not a
per-endpoint decision — both the read path and the write path derive from the
single declaration, which is what stops them disagreeing about the same column.

### Measurement Kind

What a [Measured Field](#measured-field) holds: a Training Weight, a Body
Weight, or a Height. The kind determines the canonical storage unit and the
imperial display step.

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
