<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Models\User;
use App\Services\WorkoutSession\SessionProgression;
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
     * Build resources for many rows at once, each seeded from a single batched
     * progression lookup. Use this instead of ::collection() anywhere a set of
     * session exercises is serialized.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\WorkoutSessionExercise>  $sessionExercises
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function collectionForRows($sessionExercises, ?User $user)
    {
        $progression = app(SessionProgression::class)->forRows($sessionExercises, $user);

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
        $progression = app(SessionProgression::class);

        [$targets, $lastPerformance] = $this->resolveProgression($progression, $user);

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
            $progressionStatus = $progression->statusFor(
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
    private function resolveProgression(SessionProgression $progression, $user): array
    {
        $resolved = match (true) {
            $this->progression !== null => $this->progression,
            $user === null => $progression->withoutUser($this->exercise),
            default => $progression->forExercise($this->exercise, $user),
        };

        return [$resolved['targets'], $resolved['last_performance']];
    }
}
