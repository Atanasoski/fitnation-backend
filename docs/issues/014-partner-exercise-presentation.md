# 014 — Partner exercise overrides resolved three times, with a silent precondition

**Area:** back-end / models
**Severity:** low (maintainability, with a silent failure mode)
**Status:** done — `App\Services\Exercise\PartnerExerciseView`; see *Resolved* below
**Independent:** touches no file any other open issue touches.

## Problem

`app/Models/Exercise.php` carries three near-identical methods —
`getDescription()` (`:203`), `getImage()` (`:227`), `getVideo()` (`:251`). Each is
roughly twenty lines, and they differ only in which pivot column they read. All three
have the same two-branch structure: check the loaded `partners` relation, then fall back
to a pivot on the model itself.

Every call site then repeats the same three-step dance — resolve the partner, call all
three, wrap two in `Storage::url()`:

- `app/Http/Resources/Api/ExerciseResource.php:19–21`
- `app/Http/Resources/Api/WorkoutTemplateResource.php:45–51`
- `app/Http/Controllers/ExerciseController.php:92–94`
- `app/Http/Controllers/ExerciseController.php:548–550`
- `resources/views/exercises/partner/index.blade.php:288–289` (calls `getImage()` twice
  in two lines, once to test and once to render)

### The silent precondition

The interface hides a requirement it does not state: unless `partners` has been
eager-loaded, **or** the model arrived via `partner->exercises` so it carries a pivot,
both branches fall through and the method returns the exercise's default. No error, no
warning — a missing `with('partners')` shows the wrong branding, and looks exactly like
a partner who has not set an override.

## Fix

One module resolving a partner's view of an exercise. It can live on the model as a
single method, or as a small class in `app/Services/` — the implementer's call, but the
interface matters more than the location:

```php
// partner in, resolved presentation out
$view = PartnerExerciseView::of($exercise, $partner);
$view->description;   // override or default
$view->imageUrl;      // Storage::url applied, or null
$view->videoUrl;
```

Two properties of the interface are the point:

1. **One call, not three.** Callers stop repeating the dance.
2. **The eager-load requirement is satisfied inside, not assumed.** Load `partners` if
   it is not loaded, so a caller who forgets cannot silently get defaults. If that is
   unacceptable for a list endpoint, then *throw* on an unloaded relation rather than
   returning a default — a loud failure beats wrong branding.

`Storage::url()` moves inside, so the five call sites stop applying it individually.

## Tests

There is no coverage of the override resolution today.

- Partner with an override for each of description, image and video: all three resolve
  to the override.
- Partner with no override: all three resolve to the exercise default.
- A *different* partner's override is not returned.
- **The precondition test:** the same exercise fetched without eager-loading `partners`
  must give the same answer as one fetched with it. This fails today, and is the
  strongest reason to do this work.
- An exercise reached via `partner->exercises` (pivot on the model) resolves correctly —
  this is the second branch, and the one most likely to be broken by a careless rewrite.

## Notes

- Check the Blade view at `resources/views/exercises/partner/index.blade.php:288` when
  changing the return shape; it calls the method twice in two lines.
- `app/Services/WorkoutSession/SessionDetail.php` is the precedent for module shape and
  naming if you go the class route.
- Lowest-value item in the backlog. The duplication is real but cheap, and partner
  branding is not currently a hot spot — take this only when the surrounding code is
  already open.

## Resolved

`App\Services\Exercise\PartnerExerciseView` is a readonly value object carrying
`description`, `imageUrl`, `videoUrl` and three `has*Override` flags. `of()`
resolves one exercise; `forExercises()` resolves many in a single query, which
matters because `WorkoutTemplateResource` serializes a whole template's worth at
once and would otherwise load `partners` per exercise.

The three twenty-line methods on `Exercise` are gone — a grep for
`getDescription(`, `getImage(` or `getVideo(` across `app/` and
`resources/views/` returns nothing. `Storage::url()` moved inside, so the five
call sites stopped applying it individually.

The precondition is satisfied rather than assumed, and there is a test for
exactly that:
`test_the_answer_does_not_depend_on_the_caller_eager_loading_partners`. The
second branch is covered too
(`test_an_exercise_reached_through_the_partner_resolves_from_its_own_pivot`), as
is the batching (`test_resolving_many_exercises_at_once_costs_one_query`).

Covered by `tests/Feature/PartnerExerciseViewTest.php` (11 tests) and
`tests/Feature/PartnerExercisePagesTest.php`.

Landed as `fix/partner-exercise-presentation` (PR #42).

**Not picked up here:** [011](011-program-progress-leaks-into-the-resource.md)
handed the global-`auth()` reads in `WorkoutTemplateResource` to this issue and
to [015](015-measured-field-residue.md). Neither took them —
`WorkoutTemplateResource:118` still resolves the partner from `auth()`. Now
tracked as [016](016-api-resources-read-global-auth.md).
