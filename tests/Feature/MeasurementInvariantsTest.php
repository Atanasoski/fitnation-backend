<?php

namespace Tests\Feature;

use App\Enums\MeasurementKind;
use App\Enums\UnitSystem;
use App\Http\Requests\Concerns\ConvertsIncomingUnits;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\MeasuredFields;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Guards the invariant that unit conversion is symmetric: every measured field
 * converted on the way out is converted on the way in, and vice versa.
 *
 * When this broke, an imperial user saw 135 lbs, saved 135, and stored 135 kg —
 * silently, with no error. These tests make that class of omission loud.
 */
class MeasurementInvariantsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every measured column in the schema must be registered. Without this, a
     * new weight column can be added and simply never convert.
     */
    public function test_every_measured_column_in_the_schema_is_registered(): void
    {
        $registered = array_keys(MeasuredFields::all());
        $unregistered = [];

        foreach (glob(database_path('migrations/*.php')) as $migration) {
            $contents = file_get_contents($migration);

            if (! preg_match("/Schema::(?:create|table)\('([a-z_]+)'/", $contents, $tableMatch)) {
                continue;
            }

            $table = $tableMatch[1];

            foreach (MeasuredFields::measuredColumnNames() as $column) {
                $declaresColumn = preg_match(
                    "/->(?:decimal|float|double|integer|unsignedInteger)\('".preg_quote($column, '/')."'/",
                    $contents
                );

                if ($declaresColumn && ! in_array($table.'.'.$column, $registered, true)) {
                    $unregistered[] = $table.'.'.$column;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($unregistered)),
            'These measured columns are not registered in MeasuredFields, so nothing converts them: '
            .implode(', ', array_unique($unregistered))
        );
    }

    /**
     * Any API FormRequest that validates a measured column name must convert
     * it. This is the check that would have caught the session-exercise and
     * template-exercise write paths shipping unconverted.
     */
    public function test_every_api_request_accepting_a_measured_field_converts_it(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($this->formRequestClassesFor($route) as $requestClass) {
                $measured = $this->measuredRuleKeysIn($requestClass);

                if ($measured === []) {
                    continue;
                }

                $usesTrait = in_array(
                    ConvertsIncomingUnits::class,
                    class_uses_recursive($requestClass),
                    true
                );

                if (! $usesTrait) {
                    $offenders[] = $requestClass.' (accepts '.implode(', ', $measured).')';
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            "These API requests accept a measured field without converting it, so imperial input is stored raw:\n"
            .implode("\n", array_unique($offenders))
        );
    }

    /**
     * The core invariant, at the service level, across every registered kind:
     * a value that has already been through a display round trip must survive
     * every subsequent round trip unchanged.
     *
     * Identity is deliberately NOT asserted on the first hop — display rounding
     * legitimately moves 137 lbs to 135. What must never happen is a value that
     * keeps moving, which is both the 2.2x asymmetry and slow save-cycle drift.
     */
    public function test_display_round_trip_reaches_a_fixed_point_for_every_kind(): void
    {
        $service = app(UnitConversionService::class);

        foreach (MeasurementKind::cases() as $kind) {
            foreach ([1.0, 47.3, 70.08, 102.5, 137.0, 180.0] as $canonical) {
                $displayed = $service->toDisplay($canonical, $kind, UnitSystem::Imperial);
                $restored = $service->toStorage((float) $displayed, $kind, UnitSystem::Imperial);

                // Second hop must be a fixed point.
                $displayedAgain = $service->toDisplay($restored, $kind, UnitSystem::Imperial);
                $restoredAgain = $service->toStorage((float) $displayedAgain, $kind, UnitSystem::Imperial);

                $this->assertEquals(
                    $displayed,
                    $displayedAgain,
                    "{$kind->value}: display value drifted on re-save ({$canonical} canonical)"
                );
                $this->assertEqualsWithDelta(
                    $restored,
                    $restoredAgain,
                    0.001,
                    "{$kind->value}: stored value drifted on re-save ({$canonical} canonical)"
                );
            }
        }
    }

    /**
     * The same fixed-point property, end to end through real HTTP, for the
     * profile fields. What the user sees is what a re-save stores.
     */
    public function test_profile_measurements_are_stable_across_repeated_saves(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['weight' => 183.7, 'height' => 71])
            ->assertOk();

        $first = $this->actingAs($user, 'sanctum')->getJson('/api/profile');
        $seenWeight = $first->json('user.profile.weight');
        $seenHeight = $first->json('user.profile.height');

        // Save back exactly what was displayed, three times over.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')
                ->patchJson('/api/profile', ['weight' => $seenWeight, 'height' => $seenHeight])
                ->assertOk();

            $again = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

            $this->assertEquals($seenWeight, $again->json('user.profile.weight'), 'body weight drifted on re-save');
            $this->assertEquals($seenHeight, $again->json('user.profile.height'), 'height drifted on re-save');
        }
    }

    /**
     * The same property for a template exercise target weight — the field whose
     * read side converted while its write side did not.
     */
    public function test_template_target_weight_is_stable_across_repeated_saves(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        $exercise = Exercise::factory()->create();

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        $read = fn () => $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-templates/{$template->id}")
            ->json('data.exercises.0.pivot.target_weight');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}", ['target_weight' => 137])
            ->assertOk();

        $seen = $read();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')
                ->putJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}", ['target_weight' => $seen])
                ->assertOk();

            $this->assertEquals($seen, $read(), 'template target weight drifted on re-save');
        }
    }

    /**
     * A logged set's weight, end to end.
     */
    public function test_logged_set_weight_is_stable_across_repeated_saves(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->create();
        $session = WorkoutSession::factory()->create(['user_id' => $user->id]);
        $session->workoutSessionExercises()->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        $logged = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/sets", [
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 137,
                'reps' => 10,
            ]);
        $logged->assertSuccessful();
        $setId = $logged->json('data.id');

        $read = fn () => $this->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->json('data.exercises.0.logged_sets.0.weight');

        $seen = $read();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')
                ->putJson("/api/workout-sessions/{$session->id}/sets/{$setId}", [
                    'weight' => $seen,
                    'reps' => 10,
                ])
                ->assertSuccessful();

            $this->assertEquals($seen, $read(), 'logged set weight drifted on re-save');
        }
    }

    /**
     * An unregistered column must fail loudly rather than pass through raw.
     */
    public function test_formatting_an_unregistered_column_throws(): void
    {
        $this->expectException(\LogicException::class);

        app(UnitConversionService::class);

        $resource = new class(null) extends \Illuminate\Http\Resources\Json\JsonResource
        {
            use \App\Http\Resources\Concerns\FormatsMeasurements;

            public function probe(): void
            {
                $this->formatMeasured(100.0, 'some_table', 'not_a_measured_column', UnitSystem::Imperial);
            }
        };

        $resource->probe();
    }

    /**
     * Measured column names this request declares as validation rule keys.
     *
     * Read from source rather than by calling rules(), because some requests
     * build their rules from the authenticated user and cannot be instantiated
     * outside a real request.
     *
     * @return array<int, string>
     */
    private function measuredRuleKeysIn(string $requestClass): array
    {
        $file = (new ReflectionClass($requestClass))->getFileName();

        if ($file === false) {
            return [];
        }

        $source = file_get_contents($file);
        $found = [];

        foreach (MeasuredFields::measuredColumnNames() as $column) {
            if (preg_match("/'".preg_quote($column, '/')."'\s*=>/", $source)) {
                $found[] = $column;
            }
        }

        return $found;
    }

    /**
     * @return array<int, class-string<\Illuminate\Foundation\Http\FormRequest>>
     */
    private function formRequestClassesFor(\Illuminate\Routing\Route $route): array
    {
        $action = $route->getAction('uses');

        if (! is_string($action) || ! str_contains($action, '@')) {
            return [];
        }

        [$controller, $method] = explode('@', $action);

        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            return [];
        }

        $classes = [];

        foreach ((new ReflectionClass($controller))->getMethod($method)->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (is_subclass_of($name, \Illuminate\Foundation\Http\FormRequest::class)) {
                $classes[] = $name;
            }
        }

        return $classes;
    }
}
