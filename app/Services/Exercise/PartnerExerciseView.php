<?php

namespace App\Services\Exercise;

use App\Models\Exercise;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * One partner's view of one exercise: the description, image and video they
 * see, with any override they have set already applied and Storage::url()
 * already resolved.
 *
 * This replaces Exercise::getDescription/getImage/getVideo, three near
 * identical methods that every call site invoked in turn and then wrapped two
 * of in Storage::url(). More importantly it closes their silent precondition:
 * they resolved an override only if the caller had eager-loaded `partners` or
 * had reached the exercise through `partner->exercises`, and otherwise
 * returned the default with no error — a missing with('partners') showed the
 * wrong branding and looked exactly like a partner who had set no override.
 *
 * Here the relation is loaded if it is missing, so a caller who forgets pays a
 * query rather than getting a wrong answer. Callers serializing more than one
 * exercise should use forExercises(), or eager-load `partners` themselves, to
 * keep that one query from becoming one per exercise.
 *
 * The one case this cannot rescue: a `partners` relation that was eager-loaded
 * constrained to a *different* partner reads as "this partner has no link".
 * Constrain the eager load to the partner you are going to ask about, or leave
 * it unconstrained. Pinned by a test so it is a stated contract, not a
 * surprise.
 *
 * The hasXOverride flags answer "has this partner set their own?", which is a
 * different question from what they see — a partner with no override still
 * sees the exercise default. The partner exercise pages use them for their
 * "Custom Set" / "Using Default" badges, so nothing has to reach for the pivot
 * a second time.
 */
final class PartnerExerciseView
{
    private function __construct(
        public readonly ?string $description,
        public readonly ?string $imageUrl,
        public readonly ?string $videoUrl,
        public readonly bool $hasDescriptionOverride,
        public readonly bool $hasImageOverride,
        public readonly bool $hasVideoOverride,
    ) {}

    public static function of(Exercise $exercise, ?Partner $partner): self
    {
        $pivot = $partner === null ? null : self::pivotFor($exercise, $partner);

        return new self(
            description: $pivot?->description ?? $exercise->description,
            imageUrl: self::url($pivot?->image ?? $exercise->image),
            videoUrl: self::url($pivot?->video ?? $exercise->video),
            hasDescriptionOverride: $pivot?->description !== null,
            hasImageOverride: $pivot?->image !== null,
            hasVideoOverride: $pivot?->video !== null,
        );
    }

    /**
     * Resolve a whole set of exercises against one partner, loading `partners`
     * for all of them in a single query rather than one per exercise.
     *
     * @param  iterable<int, Exercise>  $exercises
     * @return Collection<int, self> keyed by exercise id
     */
    public static function forExercises(iterable $exercises, ?Partner $partner): Collection
    {
        $models = new EloquentCollection(collect($exercises)->all());

        if ($partner !== null) {
            $models
                ->reject(fn (Exercise $exercise) => self::ownPivotFor($exercise, $partner) !== null)
                ->loadMissing('partners');
        }

        return $models->mapWithKeys(
            fn (Exercise $exercise) => [$exercise->getKey() => self::of($exercise, $partner)]
        );
    }

    /**
     * The partner_exercises row carrying this partner's overrides, or null if
     * they have set none — including when they are not linked to the exercise
     * at all, which resolves to the defaults either way.
     */
    private static function pivotFor(Exercise $exercise, Partner $partner): ?Model
    {
        // Reached via partner->exercises: the pivot rides on the model itself,
        // so there is nothing to load.
        $own = self::ownPivotFor($exercise, $partner);

        if ($own !== null) {
            return $own;
        }

        // Satisfy the precondition rather than assume it. A caller who forgot
        // with('partners') pays one query here; the old methods silently
        // returned the exercise default instead.
        $exercise->loadMissing('partners');

        return $exercise->partners->firstWhere('id', $partner->getKey())?->pivot;
    }

    /**
     * The pivot the model is already carrying, if it is this partner's. Any
     * other pivot — a workout template's, say — has no partner_id and is not
     * ours to read.
     */
    private static function ownPivotFor(Exercise $exercise, Partner $partner): ?Model
    {
        $pivot = $exercise->pivot;

        if ($pivot === null || ! isset($pivot->partner_id)) {
            return null;
        }

        return (int) $pivot->partner_id === (int) $partner->getKey() ? $pivot : null;
    }

    private static function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::url($path);
    }
}
