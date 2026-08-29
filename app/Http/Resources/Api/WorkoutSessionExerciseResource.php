<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Models\User;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionExerciseResource extends JsonResource
{
    use FormatsMeasurements;

    /**
     * Pre-resolved progression data for this row, as built by
     * WorkoutSessionResource::resolveProgression().
     *
     * Shaped as ['targets' => array, 'last_performance' => ?array]. When null,
     * this resource resolves its own — correct, but one history lookup per row,
     * so only single-row endpoints should rely on it.
     *
     * @var array{targets: array<string, mixed>, last_performance: array<string, mixed>|null}|null
     */
    private ?array $progression = null;

    /**
     * Hand this row its share of a batch-resolved progression.
     *
     * Deliberately a setter rather than a constructor argument: JsonResource
     * builds collections with mapInto(), which passes the collection key as the
     * second constructor argument, so an optional second parameter would be
     * filled with an int whenever ::collection() is used.
     *
     * @param  array{targets: array<string, mixed>, last_performance: array<string, mixed>|null}  $progression
     */
    public function withProgression(array $progression): static
    {
        $this->progression = $progression;

        return $this;
    }

    /**
     * Resolve progression for a set of session-exercise rows in one batch.
     *
     * One history query for the whole set instead of one (in fact two) per row.
     *
     * @param  iterable<int, \App\Models\WorkoutSessionExercise>  $sessionExercises
     * @return array<int, array{targets: array<string, mixed>, last_performance: array<string, mixed>|null}>
     */
    public static function batchProgression(iterable $sessionExercises, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        $exercises = collect($sessionExercises)
            ->map(fn ($sessionExercise) => $sessionExercise->exercise)
            ->filter()
            ->unique('id');

        if ($exercises->isEmpty()) {
            return [];
        }

        $calculator = app(ProgressionCalculatorService::class);
        $experience = $user->profile?->training_experience;
        $lastPerformances = $calculator->getLastPerformanceForExercises($exercises, $user);

        $progression = [];

        foreach ($exercises as $exercise) {
            $lastPerformance = $lastPerformances[$exercise->id] ?? null;

            $progression[$exercise->id] = [
                'targets' => $calculator->calculateTargetsFrom($exercise, $user, $experience, $lastPerformance),
                'last_performance' => $lastPerformance,
            ];
        }

        return $progression;
    }

    /**
     * Build resources for many rows at once, each seeded from a single batched
     * progression lookup. Use this instead of ::collection() anywhere a set of
     * session exercises is serialized.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\WorkoutSessionExercise>  $sessionExercises
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function collectionForRows($sessionExercises, ?User $user)
    {
        $progression = self::batchProgression($sessionExercises, $user);

        return collect($sessionExercises)->map(
            fn ($sessionExercise) => self::forRow($sessionExercise, $progression[$sessionExercise->exercise_id] ?? null)
        )->values();
    }

    /**
     * Build one row's resource, seeded with its batched progression when there
     * is one.
     *
     * @param  array{targets: array<string, mixed>, last_performance: array<string, mixed>|null}|null  $progression
     */
    public static function forRow($sessionExercise, ?array $progression): self
    {
        $resource = new self($sessionExercise);

        return $progression === null ? $resource : $resource->withProgression($progression);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $progressionCalculator = app(ProgressionCalculatorService::class);

        [$targets, $lastPerformance] = $this->resolveProgression($progressionCalculator, $user);

        $targetSets = $this->target_sets;
        $minTargetReps = $this->min_target_reps;
        $maxTargetReps = $this->max_target_reps;
        $restSeconds = $this->rest_seconds;

        // target_weight is always taken from the progression calculator so it
        // reflects the user's latest completed session, not a stale stored value.
        $targetWeight = $targets['target_weight'];

        if (! $targetSets) {
            $targetSets = $targets['target_sets'];
        }
        if (! $minTargetReps) {
            $minTargetReps = $targets['min_target_reps'];
        }
        if (! $maxTargetReps) {
            $maxTargetReps = $targets['max_target_reps'];
        }
        if (! $restSeconds) {
            $restSeconds = $targets['rest_seconds'];
        }
        if (($targets['progression_mode'] ?? 'double_progression') === 'total_reps') {
            $minTargetReps = null;
            $maxTargetReps = null;
        }

        $progressionStatus = 'no_history';

        if ($user) {
            // Pure: reads the already-loaded equipment type, issues no queries.
            $progressionStatus = $progressionCalculator->getProgressionStatus(
                $lastPerformance,
                (int) ($minTargetReps ?? 0),
                (int) ($maxTargetReps ?? 0),
                $this->exercise
            );
        }

        return [
            'id' => $this->id,
            'workout_session_id' => $this->workout_session_id,
            'exercise_id' => $this->exercise_id,
            'exercise' => $this->whenLoaded('exercise', function () {
                return new ExerciseResource($this->exercise);
            }),
            'order' => $this->order,
            'target_sets' => $targetSets,
            'min_target_reps' => $minTargetReps,
            'max_target_reps' => $maxTargetReps,
            'progression_mode' => $targets['progression_mode'],
            'progression_status' => $progressionStatus,
            'target_weight' => $this->formatMeasured($targetWeight, 'workout_session_exercises', 'target_weight', $user?->unitSystem()),
            'total_reps_previous' => $targets['total_reps_previous'],
            'total_reps_target' => $targets['total_reps_target'],
            'rest_seconds' => $restSeconds,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Use the injected progression when the caller batched it, otherwise fall
     * back to resolving this single row's own.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function resolveProgression(ProgressionCalculatorService $progressionCalculator, $user): array
    {
        if ($this->progression !== null) {
            return [$this->progression['targets'], $this->progression['last_performance']];
        }

        if (! $user) {
            return [$this->defaultTargets(), null];
        }

        $lastPerformance = $progressionCalculator->getLastPerformance($this->exercise, $user);

        $targets = $progressionCalculator->calculateTargetsFrom(
            $this->exercise,
            $user,
            $user->profile?->training_experience,
            $lastPerformance
        );

        return [$targets, $lastPerformance];
    }

    /**
     * Targets used when there is no authenticated user to calculate against.
     *
     * @return array<string, mixed>
     */
    private function defaultTargets(): array
    {
        return [
            'progression_mode' => 'double_progression',
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'total_reps_previous' => null,
            'total_reps_target' => null,
            'rest_seconds' => $this->exercise->default_rest_sec ?? 90,
        ];
    }
}
