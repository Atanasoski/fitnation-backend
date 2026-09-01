<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Services\Plan\ProgramProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class WorkoutTemplateResource extends JsonResource
{
    use FormatsMeasurements;

    /**
     * The id of the user's most recent completed session against this template,
     * as already resolved by a batched lookup — see ProgramProgress.
     *
     * Deliberately a setter rather than a constructor argument: JsonResource
     * builds collections with mapInto(), which passes the collection key as the
     * second constructor argument, so an optional second parameter would be
     * filled with an int whenever ::collection() is used.
     */
    private bool $lastCompletedSessionIdWasSeeded = false;

    private ?int $lastCompletedSessionId = null;

    /**
     * Hand this template its share of a batched completion lookup.
     *
     * A seeded null is a real answer — the user has never completed this
     * template — which is why the seeding is tracked separately rather than
     * inferred from the value.
     */
    public function withLastCompletedSessionId(?int $sessionId): static
    {
        $this->lastCompletedSessionIdWasSeeded = true;
        $this->lastCompletedSessionId = $sessionId;

        return $this;
    }

    /**
     * Serialize one template with its completion already resolved by a
     * ProgramProgress. A null user leaves the template to answer for itself,
     * where it correctly omits the key.
     */
    public static function forTemplate(
        WorkoutTemplate $template,
        ?User $user,
        ProgramProgress $progress,
    ): self {
        $resource = new self($template);

        if ($user === null) {
            return $resource;
        }

        return $resource->withLastCompletedSessionId(
            $progress->lastCompletedSessionId($template)
        );
    }

    /**
     * Build resources for many templates at once, each seeded from the one
     * batched completion lookup. Use this instead of ::collection() anywhere a
     * set of templates is serialized: left to themselves, each issues its own
     * query.
     *
     * @param  \Illuminate\Support\Collection<int, WorkoutTemplate>  $templates
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function collectionForTemplates(
        $templates,
        ?User $user,
        ProgramProgress $progress,
    ) {
        return collect($templates)->map(
            fn (WorkoutTemplate $template) => self::forTemplate($template, $user, $progress)
        );
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'name' => $this->name,
            'description' => $this->description,
            'day_of_week' => $this->day_of_week,
            'week_number' => $this->week_number,
            'order_index' => $this->order_index,
            'last_completed_session_id' => $this->lastCompletedSessionIdWasSeeded
                ? $this->lastCompletedSessionId
                : $this->when(
                    $request->user() !== null,
                    fn () => ProgramProgress::lastCompletedSessionIdFor(
                        $this->resource,
                        $request->user()
                    )
                ),
            'plan' => $this->whenLoaded('plan', function () {
                return new PlanResource($this->plan);
            }),
            'exercises' => $this->whenLoaded('exercises', function () {
                $partner = auth()->user()?->partner;

                return $this->exercises->map(function ($exercise) use ($partner) {
                    $image = $exercise->getImage($partner);
                    $video = $exercise->getVideo($partner);

                    return [
                        'id' => $exercise->id,
                        'name' => $exercise->name,
                        'description' => $exercise->getDescription($partner),
                        'image' => $image ? Storage::url($image) : null,
                        'video' => $video ? Storage::url($video) : null,
                        'muscle_group_image' => $exercise->muscle_group_image ? Storage::url($exercise->muscle_group_image) : null,
                        'default_rest_sec' => $exercise->default_rest_sec,
                        'category' => $exercise->category ? new CategoryResource($exercise->category) : null,
                        'muscle_groups' => $exercise->relationLoaded('muscleGroups')
                            ? MuscleGroupResource::collection($exercise->muscleGroups)
                            : [],
                        'pivot' => [
                            'id' => $exercise->pivot->id,
                            'order' => $exercise->pivot->order,
                            'target_sets' => $exercise->pivot->target_sets,
                            'min_target_reps' => $exercise->pivot->min_target_reps,
                            'max_target_reps' => $exercise->pivot->max_target_reps,
                            'target_weight' => $this->formatMeasured($exercise->pivot->target_weight, 'workout_template_exercises', 'target_weight', auth()->user()?->unitSystem()),
                            'rest_seconds' => $exercise->pivot->rest_seconds,
                        ],
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
