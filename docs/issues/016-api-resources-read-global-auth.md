# 016 — API resources read the global `auth()` instead of the request

**Area:** back-end / API serialization
**Severity:** low
**Status:** open
**Found:** while reviewing the merges for [011](011-program-progress-leaks-into-the-resource.md),
[014](014-partner-exercise-presentation.md) and [015](015-measured-field-residue.md)

## Why this exists

011 fixed `ProgramResource` to take `$request->user()` and noted that the same
reads remained in `WorkoutTemplateResource`, handing them to 014 and 015 on the
grounds that those issues owned the payload areas concerned. Both landed without
taking them. This issue exists so the leftover has an owner rather than living
in a closed issue's footnote.

## Problem

Four resources resolve the current user from the global helper rather than from
the request they are handed:

| File:line | Reads | For |
|---|---|---|
| `app/Http/Resources/Api/WorkoutTemplateResource.php:118` | `auth()->user()?->partner` | which partner's exercise presentation to show |
| `app/Http/Resources/Api/WorkoutTemplateResource.php:142` | `auth()->user()?->unitSystem()` | formatting `target_weight` |
| `app/Http/Resources/Api/SetLogResource.php:26` | `auth()->user()?->unitSystem()` | formatting `weight` |
| `app/Http/Resources/Api/ExerciseResource.php:19` | `auth()->user()?->partner` | which partner's presentation to show |

Two costs:

1. **They cannot be serialized outside a request.** A queue job, a console
   command or a test that renders one of these gets default images and Canonical
   Units rather than the user's, silently — no error, just the wrong answer. This
   is the same shape of failure as the eager-load precondition
   `SessionDetail` was built to remove.
2. **The codebase has two conventions for one thing.** Adjacent files disagree:
   `WorkoutSessionExerciseResource` uses `$request->user()`, `SetLogResource`
   uses `auth()`. A reader cannot tell which is intended.

Note this is *not* currently a live bug. Every one of these is reached through an
HTTP request today, where `auth()` and `$request->user()` agree.

## Fix

Take the user from `$request` in `toArray(Request $request)`.

The obstacle is nesting: these resources are built inside `whenLoaded()` closures
and `::collection()` calls where the parent has the request but the child is
constructed without it. `WorkoutTemplateResource` already solves the equivalent
problem for `last_completed_session_id` with a seeding setter plus a
`collectionForTemplates()` factory — follow that pattern rather than inventing a
second one, and read `PartnerExerciseView::forExercises()` for how the batched
partner resolution is already threaded through.

Check whether `PartnerExerciseView` and `FormatsMeasurements` should take the
user as an argument rather than each caller resolving it.

## Tests

- Render each of the four resources outside a request — no `actingAs`, an
  explicitly constructed `Request` with a user resolver — and assert the output
  matches what the HTTP path produces for the same user. This fails today for
  all four.
- An imperial user's `SetLogResource` weight must be in pounds when serialized
  from a non-request context.
- Existing coverage must stay green untouched:
  `tests/Feature/UnitSystemTest.php`, `MeasurementInvariantsTest.php`,
  `PartnerExerciseViewTest.php`.

## Notes

- Low priority and no user-visible symptom. Take it when one of these resources
  is open for another reason, or if something ever needs to serialize a template
  or a set log off the request path.
- Do not change any payload while doing this. The output must be identical; only
  where the user comes from changes.
