---
name: Unit System (kg/lbs, cm/in) Implementation - Backend Phase
overview: Add lbs/ft-in display and input support to the API while keeping kg/cm as the sole canonical storage format. Conversion happens strictly at the HTTP boundary (Resources on read, FormRequests on write) - Eloquent models, FitnessMetricsService, and ProgressionCalculatorService never change and never see anything but raw kg/cm.
todos:
  - id: phase-1-schema
    content: "Phase 1: UnitSystem enum, migration, UserProfile model + factory updates"
    status: pending
  - id: phase-2-conversion-service
    content: "Phase 2: UnitConversionService with unit tests"
    status: pending
  - id: phase-3-read-path
    content: "Phase 3: FormatsWeights trait + UserProfileResource - unit-aware API output"
    status: pending
  - id: phase-4-write-path
    content: "Phase 4: ProfileUpdateRequest, LogSetRequest, UpdateSetRequest - unit-aware input"
    status: pending
  - id: phase-5-tests
    content: "Phase 5: Feature tests + regression verification"
    status: pending
---

# Unit System: Backend Phase Implementation

Organized into 5 phases for incremental execution and testing. This is **backend only** - no changes to `front-end/apps/web`, `front-end/apps/mobile`, `front-end/packages/shared`, or any Blade admin/partner views (`resources/views/users/show.blade.php` etc. stay hardcoded metric - that's staff tooling, out of scope).

## Ground truth this plan relies on (from codebase research - do not re-derive, just use)

- **Migration convention**: anonymous class (`return new class extends Migration`), `Schema::table()` for adding a column to an existing table, `->after('column')` to control column order, `down()` always fully implemented. See `database/migrations/2026_06_21_000001_add_social_auth_to_users_table.php` and `database/migrations/2026_05_02_175219_add_soft_deletes_to_users_table.php` as literal templates.
- **Enum-backed columns are always `string`, never DB-level `enum()`**, cast to a PHP backed enum via the model's `casts()` method - see `UserProfile::casts()` (`gender`, `fitness_goal`, `training_experience` are all `string` columns cast to `App\Enums\*` classes). `unit_system` follows this exact pattern.
- **`App\Http\Requests\Api\ProfileUpdateRequest`** (NOT the non-Api one at `app/Http/Requests/ProfileUpdateRequest.php`, which is the Breeze web-session profile form and is out of scope) is the **only** write path for `height`/`weight`. `CompleteOnboardingRequest` only validates `plan_name` and never touches height/weight or creates a `UserProfile` row - it delegates to `WelcomePlanGenerationService`. **Do not add unit_system handling to `CompleteOnboardingRequest` or `OnboardingController` - confirmed out of scope for this phase.**
- **`FormatsWeights` trait** (`app/Http/Resources/Concerns/FormatsWeights.php`) has exactly 3 consumers: `SetLogResource`, `WorkoutSessionExerciseResource`, `WorkoutTemplateResource` - confirmed via grep, no other call sites exist. This is the single choke point for training-weight display formatting.
- **`WorkoutSessionExerciseResource`'s `target_weight`** is always live-recomputed from `ProgressionCalculatorService::calculateTargets()` (see the comment at that file's line ~46-47: "target_weight is always taken from the progression calculator") - never read from a stored column. `ProgressionCalculatorService` itself is untouched by this plan; its existing per-equipment kg-rounding (`roundToEquipmentIncrement`/`getWeightIncrement`) happens upstream and this plan's flat-step lbs rounding composes on top of it at display time only.
- **`WorkoutTemplateResource`'s `target_weight`** (`$exercise->pivot->target_weight`) IS a real stored `decimal(8,2)` column on `workout_template_exercises`, written by `StoreWorkoutTemplateExerciseRequest`/`UpdateWorkoutTemplateExerciseRequest`/`WorkoutTemplateExerciseController`/`Api\WorkoutTemplateController`/workout-generation services. This plan converts it for **display only** (via the same `FormatsWeights` trait extension - it's already a consumer). Converting the **write side** of those template-editing requests is explicitly **out of scope** - that's coach/admin tooling for building templates, same precedent as the admin dashboard staying metric.

  > **AMENDED during implementation.** This was wrong, and was reversed. The exclusion was incoherent: the *read* side of `workout_template_exercises.target_weight` IS converted (above), and the stored pivot value IS read back — so leaving the write side metric meant an imperial user saw `135`, saved `135`, and stored `135 kg`. Silent data corruption with no error. The `Api\` template-exercise write path now converts. The **web/Blade** requests (`App\Http\Requests\StoreWorkoutTemplateExerciseRequest` / `UpdateWorkoutTemplateExerciseRequest`) remain metric and untouched — that part of the reasoning held. The same asymmetry was also found and fixed on `AddSessionExerciseRequest`/`UpdateSessionExerciseRequest`, which this plan missed entirely.
- **Resources get the authenticated user two ways** in this codebase: `WorkoutSessionExerciseResource` uses the injected `$request->user()` (already resolved locally as `$user` at that file's line 21 - reuse it, don't re-resolve). `WorkoutTemplateResource`, `ProgramResource`, `ExerciseResource` all use the global `auth()->user()` helper. Match whichever style each file already uses rather than introducing a third style.
- **`formatWeight()` is `private`** inside the trait - fine, traits paste their methods into the composing class, so this stays private and is still callable from each of the 3 consumer classes without visibility changes.
- **FormRequests can be constructor-injected** with services (confirmed pattern: `OnboardingController` constructor-injects `WelcomePlanGenerationService`) - use constructor injection for `UnitConversionService` in the FormRequest classes in Phase 4.
  > **AMENDED during implementation.** Superseded. The six conversion sites were collapsed into an `App\Http\Requests\Concerns\ConvertsIncomingUnits` trait, which resolves the service via `app()` internally. The four FormRequest constructors existed only to feed that one call and were deleted. Resources are NOT container-resolved (instantiated directly via `new XResource($model)`), so they must use the `app(UnitConversionService::class)` helper instead in Phase 3.
- **Validation-transform hook**: Laravel FormRequests support `prepareForValidation()`, which runs before `rules()` is evaluated and can call `$this->merge([...])` to rewrite input. This is the mechanism for Phase 4: convert incoming lbs/inches to kg/cm inside `prepareForValidation()`, so the existing kg/cm-based validation bounds (`weight: min:1,max:500`, `height: min:50,max:300`) keep working completely unchanged - no parallel imperial bounds needed.
- **Test conventions**: PHPUnit-native class-based tests (`class X extends TestCase { use RefreshDatabase; public function test_...(): void {...} }`), NOT Pest - confirmed no Pest config/usage anywhere in `tests/`. Sanctum auth pattern: `$this->actingAs($user, 'sanctum')->getJson(...)`/`patchJson(...)`. Assertion pattern: `assertOk()` + `assertJsonStructure([...])`, or `$response->json('data.field')` dot-path pulls followed by plain PHPUnit assertions. Literal template to copy structure from: `tests/Feature/FitnessMetricsTest.php` (uses `$user->profile->update([...])` for profile setup).
- **`UserProfileFactory`** (`database/factories/UserProfileFactory.php`) currently has no `unit_system` key - needs a default added plus an `imperial()` state method for test ergonomics.

---

## Phase 1: Schema & Model Layer

**Goal:** Add the `UnitSystem` enum and the `unit_system` column, wire it into the model and factory.

### 1.1 Create `App\Enums\UnitSystem`

Create `app/Enums/UnitSystem.php`:

```php
<?php

namespace App\Enums;

enum UnitSystem: string
{
    case Metric = 'metric';
    case Imperial = 'imperial';
}
```

### 1.2 Migration: add `unit_system` to `user_profiles`

Create `database/migrations/2026_08_04_000001_add_unit_system_to_user_profiles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('unit_system')->default('metric')->after('workout_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('unit_system');
        });
    }
};
```

Deliberately `->default('metric')` and NOT nullable - unlike `gender`/`fitness_goal`/`training_experience` on this same table (all nullable), `unit_system` has no valid "unknown" state since every rendered weight/height field must resolve to one unit or the other. The DB default means every existing row gets a correct value automatically - no backfill script needed.

### 1.3 Update `App\Models\UserProfile`

In `app/Models/UserProfile.php`:
- Add `'unit_system'` to the `$fillable` array (after `'workout_duration_minutes'`).
- Add `'unit_system' => UnitSystem::class` to the `casts()` array.
- Add `use App\Enums\UnitSystem;` import.

### 1.4 Update `database/factories/UserProfileFactory.php`

Add to `definition()`:
```php
'unit_system' => UnitSystem::Metric,
```

Add an `imperial()` state for test ergonomics:
```php
public function imperial(): static
{
    return $this->state(fn () => ['unit_system' => UnitSystem::Imperial]);
}
```

Add `use App\Enums\UnitSystem;` import.

### Files to Create/Modify

| Action | File |
|--------|------|
| Create | `app/Enums/UnitSystem.php` |
| Create | `database/migrations/2026_08_04_000001_add_unit_system_to_user_profiles_table.php` |
| Modify | `app/Models/UserProfile.php` |
| Modify | `database/factories/UserProfileFactory.php` |

### Verification checklist

- `php artisan migrate` runs clean; `php artisan migrate:rollback` then `php artisan migrate` again round-trips cleanly.
- `php artisan tinker` → `App\Models\UserProfile::factory()->create()->unit_system` returns `UnitSystem::Metric`.
- `App\Models\UserProfile::factory()->imperial()->create()->unit_system` returns `UnitSystem::Imperial`.

### Anti-pattern guards

- Do NOT use `$table->enum(...)` - this codebase's convention for enum-like fields is `string` + PHP-side cast, confirmed via `UserProfile::casts()`.
- Do NOT make the column nullable "to match the other fields" - this is a deliberate, justified deviation (see 1.2).

---

## Phase 2: Conversion Service

**Goal:** A single, plain-PHP, unit-testable service holding all conversion math and both rounding policies. No Laravel framework dependencies (no `$request`, no `auth()`) - keep it a pure calculator so Phase 5's unit tests don't need any HTTP/DB setup.

### 2.1 Create `App\Services\UnitConversionService`

Create `app/Services/UnitConversionService.php`:

```php
<?php

namespace App\Services;

use App\Enums\UnitSystem;

class UnitConversionService
{
    private const KG_TO_LBS = 2.2046226218;

    private const CM_TO_INCHES = 0.3937007874;

    private const TRAINING_WEIGHT_STEP_LBS = 5.0;

    private const BODY_WEIGHT_STEP_LBS = 0.5;

    /**
     * Format a stored kg training weight (set log weight, or a progression
     * target) for display. Metric: passthrough, trailing zeros stripped.
     * Imperial: convert to lbs, round to the nearest 5 lbs (a barbell's
     * smallest practical per-side plate increment).
     */
    public function formatTrainingWeight(string|float|null $weightKg, ?UnitSystem $unitSystem): float|int|null
    {
        if ($weightKg === null) {
            return null;
        }

        $kg = (float) $weightKg;

        if ($unitSystem !== UnitSystem::Imperial) {
            return $this->stripTrailingZeros($kg);
        }

        $lbs = $this->roundToStep($kg * self::KG_TO_LBS, self::TRAINING_WEIGHT_STEP_LBS);

        return $this->stripTrailingZeros($lbs);
    }

    /**
     * Format a stored kg body weight (profile weight) for display. Metric:
     * passthrough. Imperial: convert to lbs, round to the nearest 0.5 lb -
     * plain human-friendly precision, no plate-step logic.
     */
    public function formatBodyWeight(string|float|null $weightKg, ?UnitSystem $unitSystem): float|int|null
    {
        if ($weightKg === null) {
            return null;
        }

        $kg = (float) $weightKg;

        if ($unitSystem !== UnitSystem::Imperial) {
            return $this->stripTrailingZeros($kg);
        }

        $lbs = $this->roundToStep($kg * self::KG_TO_LBS, self::BODY_WEIGHT_STEP_LBS);

        return $this->stripTrailingZeros($lbs);
    }

    /**
     * Format a stored cm height for display. Metric: passthrough. Imperial:
     * convert to total inches, rounded to the nearest whole inch. Always a
     * single number - feet/inches display splitting is a frontend concern.
     */
    public function formatHeight(int|string|null $heightCm, ?UnitSystem $unitSystem): int|null
    {
        if ($heightCm === null) {
            return null;
        }

        $cm = (float) $heightCm;

        if ($unitSystem !== UnitSystem::Imperial) {
            return (int) $cm;
        }

        return (int) round($cm * self::CM_TO_INCHES);
    }

    /**
     * Convert an incoming weight value to kg for storage, given the unit
     * system it was submitted in. Used for both body weight and training
     * weight inputs - the conversion factor is the same either direction,
     * only the display-side rounding policy differs.
     */
    public function toKg(float $weight, UnitSystem $unitSystem): float
    {
        return $unitSystem === UnitSystem::Imperial ? $weight / self::KG_TO_LBS : $weight;
    }

    /**
     * Convert an incoming height value (inches) to cm for storage.
     */
    public function toCm(float $height, UnitSystem $unitSystem): int
    {
        return $unitSystem === UnitSystem::Imperial
            ? (int) round($height / self::CM_TO_INCHES)
            : (int) round($height);
    }

    private function roundToStep(float $value, float $step): float
    {
        return round($value / $step) * $step;
    }

    private function stripTrailingZeros(float $value): float|int
    {
        return $value == (int) $value ? (int) $value : $value;
    }
}
```

### 2.2 Unit tests

Create `tests/Unit/UnitConversionServiceTest.php` (PHPUnit-native class style, no DB needed - `Tests\TestCase` without `RefreshDatabase`):

Cover:
- `formatTrainingWeight`: metric passthrough strips trailing zeros (e.g. `100.00` → `100`, `102.50` → `102.5`); imperial rounds to nearest 5 (e.g. `100kg` → `220` or `225` - compute and assert the exact expected value); `null` → `null`.
- `formatBodyWeight`: imperial rounds to nearest 0.5 lb, distinct from the training-weight 5lb step - use a value that would round differently under each policy to prove they're actually different code paths (e.g. a kg value converting to something like `181.7` lbs: body-weight policy → `181.5`, training-weight policy → `180`).
- `formatHeight`: metric passthrough; imperial converts cm→inches rounded to nearest whole inch; `null` → `null`.
- `toKg`/`toCm`: round-trip sanity - converting a value to kg/cm and back through `formatBodyWeight`/`formatHeight` with the same unit system should land close to the original (within the rounding policy's own step size, not exact - assert bounds, not equality).
- Exact half-step tie behavior for `roundToStep` (test indirectly through `formatTrainingWeight`/`formatBodyWeight` with an input that lands exactly on a `.5` boundary) - just assert PHP's native `round()` behavior (round-half-away-from-zero) is what happens, don't invent a different tie-breaking rule.

### Files to Create/Modify

| Action | File |
|--------|------|
| Create | `app/Services/UnitConversionService.php` |
| Create | `tests/Unit/UnitConversionServiceTest.php` |

### Verification checklist

- `php artisan test tests/Unit/UnitConversionServiceTest.php` passes.
- No import of anything under `Illuminate\Http` or `Illuminate\Database` in `UnitConversionService.php` - confirms it stayed a pure calculator.

### Anti-pattern guards

- Do NOT add equipment-awareness (per-exercise-type step sizes) to the training-weight rounding - explicitly rejected for this phase, flat 5lb/0.5lb steps only.
- Do NOT round at all when `unitSystem` resolves to metric (or `null`) beyond the existing trailing-zero strip - metric values must never drift from what's stored.

---

## Phase 3: Read Path - API Resources

**Goal:** Every weight/height field the API emits gets unit-aware formatting, without touching `FitnessMetricsService` or `ProgressionCalculatorService`.

### 3.1 Extend `FormatsWeights` trait

Modify `app/Http/Resources/Concerns/FormatsWeights.php`:

```php
<?php

namespace App\Http\Resources\Concerns;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;

trait FormatsWeights
{
    /**
     * Format a training weight (kg, as stored) for display in the given
     * unit system. Delegates all conversion/rounding to UnitConversionService.
     */
    private function formatWeight(string|float|null $weight, ?UnitSystem $unitSystem = null): float|int|null
    {
        return app(UnitConversionService::class)->formatTrainingWeight($weight, $unitSystem);
    }
}
```

The `?UnitSystem $unitSystem = null` default keeps every existing call site compiling before it's updated in 3.2-3.4; `formatTrainingWeight(..., null)` takes the metric-passthrough branch, so behavior is unchanged until each call site is updated to actually pass the user's preference.

### 3.2 Update `SetLogResource`

In `app/Http/Resources/Api/SetLogResource.php`, change:
```php
'weight' => $this->formatWeight($this->weight),
```
to:
```php
'weight' => $this->formatWeight($this->weight, auth()->user()?->profile?->unit_system),
```
(This resource doesn't currently resolve any user - use the global `auth()` helper, matching the dominant convention in this codebase.)

### 3.3 Update `WorkoutSessionExerciseResource`

In `app/Http/Resources/Api/WorkoutSessionExerciseResource.php`, this file already resolves `$user = $request->user();` at the top of `toArray()` - reuse it, don't call `auth()` again:
```php
'target_weight' => $this->formatWeight($targetWeight, $user?->profile?->unit_system),
```

### 3.4 Update `WorkoutTemplateResource`

In `app/Http/Resources/Api/WorkoutTemplateResource.php`, this file already uses the global `auth()` helper elsewhere in the same method - match it:
```php
'target_weight' => $this->formatWeight($exercise->pivot->target_weight, auth()->user()?->profile?->unit_system),
```

### 3.5 Update `UserProfileResource`

In `app/Http/Resources/Api/UserProfileResource.php`:

```php
<?php

namespace App\Http\Resources\Api;

use App\Services\UnitConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conversionService = app(UnitConversionService::class);
        $unitSystem = $this->unit_system;

        return [
            'fitness_goal' => $this->fitness_goal?->value,
            'age' => $this->age,
            'gender' => $this->gender?->value,
            'height' => $conversionService->formatHeight($this->height, $unitSystem),
            'weight' => $conversionService->formatBodyWeight($this->weight, $unitSystem),
            'unit_system' => $unitSystem?->value,
            'training_experience' => $this->training_experience?->value,
            'training_days_per_week' => $this->training_days_per_week,
            'workout_duration_minutes' => $this->workout_duration_minutes,
        ];
    }
}
```

Note: `unit_system` is a new field in the response - this is the field clients read to know how to label every other number.

### Files to Create/Modify

| Action | File |
|--------|------|
| Modify | `app/Http/Resources/Concerns/FormatsWeights.php` |
| Modify | `app/Http/Resources/Api/SetLogResource.php` |
| Modify | `app/Http/Resources/Api/WorkoutSessionExerciseResource.php` |
| Modify | `app/Http/Resources/Api/WorkoutTemplateResource.php` |
| Modify | `app/Http/Resources/Api/UserProfileResource.php` |

### Verification checklist

- `grep -rn "formatWeight(" app/` still shows exactly the same 3 call sites plus the trait definition - confirms no new consumer was accidentally introduced or an old one missed.
- Manually hit `GET /api/profile` for a user with `unit_system = metric` and confirm the JSON is byte-for-byte identical to before this phase (aside from the new `unit_system` key).
- Same check with a user manually set to `unit_system = imperial` (via tinker, since there's no write path yet until Phase 4) - confirm `weight`/`height` convert, and `SetLogResource`/`WorkoutSessionExerciseResource`/`WorkoutTemplateResource` output for that user also converts.

### Anti-pattern guards

- Do NOT change `FitnessMetricsService` or `ProgressionCalculatorService` - they must keep reading `->weight`/`->height` directly off the model, untouched by anything in this phase.
- Do NOT introduce a 4th style for resolving the current user in a Resource - reuse each file's existing style (`$request->user()` where already present, `auth()->user()` elsewhere).

---

## Phase 4: Write Path - FormRequests

**Goal:** Accept incoming weight/height values in the user's unit system and convert to kg/cm before validation runs, so existing kg/cm-based bounds keep working unchanged.

### 4.1 Update `App\Http\Requests\Api\ProfileUpdateRequest`

This is the one request that owns `unit_system` (set during onboarding's profile-fill step or later editing) - read it from THIS request's own payload first (self-describing, handles onboarding where no profile row exists yet, and a simultaneous toggle+edit in one PATCH unambiguously), falling back to the stored value only if omitted.

Add to `rules()`:
```php
'unit_system' => ['sometimes', 'nullable', Rule::enum(UnitSystem::class)],
```

Add a `prepareForValidation()` method:
```php
protected function prepareForValidation(): void
{
    $unitSystem = UnitSystem::tryFrom((string) $this->input('unit_system'))
        ?? $this->user()->profile?->unit_system
        ?? UnitSystem::Metric;

    if ($unitSystem !== UnitSystem::Imperial) {
        return;
    }

    $merge = [];

    if ($this->filled('weight')) {
        $merge['weight'] = $this->conversionService->toKg((float) $this->input('weight'), UnitSystem::Imperial);
    }

    if ($this->filled('height')) {
        $merge['height'] = $this->conversionService->toCm((float) $this->input('height'), UnitSystem::Imperial);
    }

    if ($merge !== []) {
        $this->merge($merge);
    }
}
```

Constructor-inject `UnitConversionService` (matching the `OnboardingController` constructor-injection pattern already used in this codebase):
```php
public function __construct(
    private readonly UnitConversionService $conversionService,
    // ... existing constructor args, if any (check current class - it may have none)
) {
    parent::__construct();
}
```
(FormRequest constructors need `parent::__construct()` called with no extra args if the base class expects none - verify against Laravel 12's `FormRequest` constructor signature before finalizing; if `FormRequest` has no meaningful constructor args to pass through, this is safe.)

Add imports: `use App\Enums\UnitSystem;`, `use App\Services\UnitConversionService;`.

Existing validation bounds (`height: min:50,max:300`, `weight: min:1,max:500`) stay completely unchanged - they now always validate a kg/cm value because `prepareForValidation()` already converted it.

### 4.2 Update `App\Http\Controllers\Api\ProfileController::update()`

Confirm `unit_system` is included in whatever `$profileFields` whitelist array the controller uses to build `$profileData` before `updateOrCreate()` (the research found this at lines ~58-67, add `'unit_system'` to that list).

### 4.3 Update `App\Http\Requests\LogSetRequest` and `App\Http\Requests\UpdateSetRequest`

These don't carry a `unit_system` field of their own (not where a user changes their global preference) - fall back to the authenticated user's stored preference.

Add to both:
```php
protected function prepareForValidation(): void
{
    $unitSystem = $this->user()?->profile?->unit_system ?? UnitSystem::Metric;

    if ($unitSystem !== UnitSystem::Imperial || ! $this->filled('weight')) {
        return;
    }

    $this->merge([
        'weight' => $this->conversionService->toKg((float) $this->input('weight'), UnitSystem::Imperial),
    ]);
}
```

Same constructor-injection + import pattern as 4.1.

### Files to Create/Modify

| Action | File |
|--------|------|
| Modify | `app/Http/Requests/Api/ProfileUpdateRequest.php` |
| Modify | `app/Http/Controllers/Api/ProfileController.php` |
| Modify | `app/Http/Requests/LogSetRequest.php` |
| Modify | `app/Http/Requests/UpdateSetRequest.php` |

### Verification checklist

- `PATCH /api/profile` with `{"unit_system": "imperial", "weight": 200, "height": 70}` for a fresh user (no prior profile) persists `weight`/`height` as kg/cm equivalents in the DB, not the raw imperial numbers.
- `PATCH /api/profile` with just `{"weight": 205}` for a user already stored as imperial (no `unit_system` key in this payload) still converts correctly using the stored preference.
- A validation failure (e.g. an absurdly high imperial weight that would exceed 500kg once converted) still returns the existing kg-based error message - confirms bounds are checked post-conversion, not pre-conversion.
- `POST` to the set-logging endpoint with a plain numeric `weight` for an imperial user persists the kg-converted value in `workout_session_set_logs.weight`.

### Anti-pattern guards

- Do NOT add a second, parallel set of imperial validation bounds - conversion happens in `prepareForValidation()` specifically so the existing kg/cm bounds are the only bounds that ever need to exist.
- Do NOT read `unit_system` from the stored profile in `ProfileUpdateRequest` when the current payload includes its own `unit_system` value - the payload's own value must win, to handle onboarding (no stored profile yet) and simultaneous toggle+edit correctly.
- Do NOT touch the **web** `App\Http\Requests\StoreWorkoutTemplateExerciseRequest`/`UpdateWorkoutTemplateExerciseRequest` - confirmed out of scope (coach/admin template-building tooling).
  > **AMENDED during implementation.** Originally this read "Do NOT touch `Store...`/`Update...`" without qualification, which was taken to exclude the API write path too. The `Api\`-namespaced template-exercise and session-exercise requests DO convert — see the amendment at the top of this document. Only the web/Blade variants stay metric.

---

## Phase 5: Tests & Verification

**Goal:** Prove the feature works end-to-end and prove the one invariant that matters most: `FitnessMetricsService` and `ProgressionCalculatorService` outputs are unaffected by a user's unit preference.

### 5.1 Feature tests

Create `tests/Feature/UnitSystemTest.php` (class-based PHPUnit style, `use RefreshDatabase`, `actingAs($user, 'sanctum')` pattern copied from `tests/Feature/FitnessMetricsTest.php`):

- Imperial user's `GET /api/profile` returns converted `weight`/`height` and `unit_system: "imperial"`.
- Metric user's `GET /api/profile` output is unchanged from pre-feature behavior.
- Imperial user's set-log listing (whatever endpoint returns `SetLogResource` collections) shows lbs-converted, 5lb-stepped weights.
- `PATCH /api/profile` onboarding-style request (no prior profile row) with `unit_system: imperial` + weight/height in lbs/inches persists correct kg/cm in the DB (assert directly against the `user_profiles` table, not just the response).
- Logging a set with a lbs weight for an imperial user persists the correct kg value in `workout_session_set_logs`.

### 5.2 Regression test - the hard invariant

Add a test that:
1. Creates two otherwise-identical users (same completed sessions, same set logs, same profile weight in kg) - one `unit_system = metric`, one `unit_system = imperial`.
2. Calls whatever endpoint/service method exposes `FitnessMetricsService`'s 1RM/volume/strength-score output for both.
3. Asserts the raw numeric outputs are identical between the two users (not converted - identical), proving the unit preference never leaks into that service's internal kg-based math.
4. Same check for `ProgressionCalculatorService::calculateTargets()` - assert the returned `target_weight` (in kg, pre-formatting) is identical for both users; only `WorkoutSessionExerciseResource`'s final JSON output should differ.

### Files to Create/Modify

| Action | File |
|--------|------|
| Create | `tests/Feature/UnitSystemTest.php` |

### Verification checklist

- `composer test` (full suite) passes - no existing test's assertions broke (this is the real regression check for the whole plan, since `FormatsWeights`, `UserProfileResource`, `ProfileUpdateRequest`, `LogSetRequest`, `UpdateSetRequest` are all shared, high-traffic classes).
- New tests in `UnitSystemTest.php` and `UnitConversionServiceTest.php` pass.
- Manual smoke test: toggle a real test user to imperial via `PATCH /api/profile`, then hit `/api/profile`, a set-logging endpoint, and a workout-session endpoint, and visually confirm the numbers look like reasonable lbs values (not garbage from a unit mixup).

### Anti-pattern guards

- Do NOT consider this phase done if `composer test` has any failures, even in unrelated-looking tests - `FormatsWeights`/`UserProfileResource` touch enough call sites that a silent regression is easy to introduce.
- Do NOT skip the regression test in 5.2 - it's the one test that directly proves the hard architectural invariant this whole plan depends on.
