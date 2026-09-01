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

### Personal Record

A best an athlete beat in one session: the exercise, what kind of best it was,
the number that stood before and the number that beat it. Detected when a
session is completed, against that athlete's own completed sessions only.

Two kinds — a *weight* record and a *reps* record — but **one set**. Per
exercise, the session's sets that beat a prior best are gathered and the best
single one among them wins, judged by estimated one-rep max rather than by
load; only what *that* set beat is reported. So a session takes at most one of
each kind per exercise, and a set that beat both produces two records of one
performance. A heavy single and a light high-rep set are two performances, and
only the better one is celebrated.

An exercise with no history at all has no best to beat, so it records nothing:
a first-ever session is silent.

Like a [Session Detail](#session-detail), a Personal Record is **derived**,
never stored: nothing records that a record was ever set, so the same session
completed twice reports the same records twice.

_Avoid_: PR — it reads as pull request.

## Fitness Metrics

### Completed Session

A workout session whose status is `completed`. The single definition — a session
carries both a status and a `completed_at` timestamp, and only the status
decides. Nothing derived from a user's training counts a session that is not
completed, however far through it they got.

_Avoid_: finished session, logged session.

### Strength Score

How strong a user is as a multiple of their own body weight: the sum of their
best estimated one-rep max per exercise over the last 30 days, divided by their
body weight, times 100.

Relative on purpose — an absolute total would rank every heavy user above every
light one. **Best per exercise**, not per set, so extra sets of the same lift
describe the same capability once rather than reading as extra strength.

A user with no recorded body weight has no Strength Score at all, because the
whole quantity is a ratio to it.

### Strength Balance

How evenly a user spreads training volume across the seventeen tracked muscle
groups over the last 30 days.

Two independent things, multiplied: **coverage**, the fraction of groups trained
at all, and **evenness**, how uniformly volume is split among the ones that
were. Neither carries the other — three groups trained perfectly evenly is not
balance, and nor is touching all seventeen while putting nine tenths of the
volume into one.

### Weekly Progress

A user's last **full** week of training measured against the week before it:
workouts, volume, time, and a day-by-day breakdown.

The week in progress is never the subject. Comparing a Tuesday against a
finished week would make the number fall every Monday and climb back by Sunday,
which says nothing about the user.

### Partner Cohort

The people at the same partner a user's [Strength Score](#strength-score) or
[Strength Balance](#strength-balance) is ranked against: same gender, same
training experience, within five years of age, and at least five
[Completed Sessions](#completed-session) in the last 30 days.

Fewer than ten such people is no cohort and produces no percentile at all,
rather than a percentile drawn from three gym-mates.

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
