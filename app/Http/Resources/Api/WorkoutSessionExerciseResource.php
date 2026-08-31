<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Models\User;
use App\Services\WorkoutSession\SessionExerciseDetail;
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
     * Targets and progression status already resolved by SessionDetail. When
     * set, this resource decides nothing and only writes them out.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resolvedTargets = null;

    private ?string $resolvedStatus = null;

    /**
     * Serialize a row whose detail SessionDetail has already resolved.
     */
    public static function forDetail(SessionExerciseDetail $detail): self
    {
        $resource = new self($detail->row);
        $resource->resolvedTargets = $detail->targets;
        $resource->resolvedStatus = $detail->progressionStatus;

        return $resource;
    }

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

        [$targets, $progressionStatus] = $this->resolvedTargets !== null
            ? [$this->resolvedTargets, $this->resolvedStatus]
            : $this->resolveOwnDetail($user);

        return [
            'id' => $this->id,
            'workout_session_id' => $this->workout_session_id,
            'exercise_id' => $this->exercise_id,
            'exercise' => $this->whenLoaded('exercise', function () {
                return new ExerciseResource($this->exercise);
            }),
            'order' => $this->order,
            'target_sets' => $targets['target_sets'],
            'min_target_reps' => $targets['min_target_reps'],
            'max_target_reps' => $targets['max_target_reps'],
            'progression_mode' => $targets['progression_mode'],
            'progression_status' => $progressionStatus,
            'target_weight' => $this->formatMeasured($targets['target_weight'], 'workout_session_exercises', 'target_weight', $user?->unitSystem()),
            'total_reps_previous' => $targets['total_reps_previous'],
            'total_reps_target' => $targets['total_reps_target'],
            'rest_seconds' => $targets['rest_seconds'],
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
    private function resolveOwnDetail(?User $user): array
    {
        $progressions = app(SessionProgression::class);

        $resolved = match (true) {
            $this->progression !== null => $this->progression,
            $user === null => $progressions->withoutUser($this->exercise),
            default => $progressions->forExercise($this->exercise, $user),
        };

        $targets = $progressions->targetsFor($this->resource, $resolved['targets']);

        $status = $user === null
            ? 'no_history'
            // Pure: reads the already-loaded equipment type, issues no queries.
            : $progressions->statusFor(
                $resolved['last_performance'],
                (int) ($targets['min_target_reps'] ?? 0),
                (int) ($targets['max_target_reps'] ?? 0),
                $this->exercise
            );

        return [$targets, $status];
    }
}
