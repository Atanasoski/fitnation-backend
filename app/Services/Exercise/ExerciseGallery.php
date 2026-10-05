<?php

namespace App\Services\Exercise;

use App\Enums\ExerciseDifficulty;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\TargetRegion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The super admin's exercise gallery: one slice of the catalogue, chosen by
 * query parameters, with a count for every facet value (spec 023, ticket 03).
 *
 * Parameters: `q` (name search, every word), `region[]` and `equipment[]`
 * (lookup ids), `difficulty[]` (ExerciseDifficulty values), `missing=media`
 * (no image, video or muscle-group image) and `archived=1` (only Archived
 * Exercises; without it they are left out). Several values of one facet widen
 * it; different facets narrow each other.
 *
 * A facet's counts apply every other active filter but not its own, so each
 * count says how many exercises ticking that value would add or keep.
 */
final class ExerciseGallery
{
    public const PER_PAGE = 48;

    /**
     * @param  list<int>  $regions
     * @param  list<int>  $equipment
     * @param  list<ExerciseDifficulty>  $difficulties
     */
    private function __construct(
        public readonly ?string $q,
        public readonly array $regions,
        public readonly array $equipment,
        public readonly array $difficulties,
        public readonly bool $missingMedia,
        public readonly bool $archived,
    ) {}

    /**
     * Read the filters from a query string's parameters. Anything unknown or
     * malformed is dropped.
     *
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        $ids = fn (string $key) => array_values(array_unique(array_map('intval', array_filter(
            (array) ($query[$key] ?? []),
            fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0,
        ))));

        $difficulties = [];
        foreach ((array) ($query['difficulty'] ?? []) as $value) {
            $difficulty = is_string($value) ? ExerciseDifficulty::tryFrom($value) : null;
            if ($difficulty && ! in_array($difficulty, $difficulties, true)) {
                $difficulties[] = $difficulty;
            }
        }

        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';

        return new self(
            q: $q === '' ? null : $q,
            regions: $ids('region'),
            equipment: $ids('equipment'),
            difficulties: $difficulties,
            missingMedia: ($query['missing'] ?? null) === 'media',
            archived: (string) ($query['archived'] ?? '') === '1',
        );
    }

    /**
     * The filters as query parameters: what the gallery's links and the
     * editor's "back" carry, so a slice survives a save.
     *
     * @return array<string, string|list<string>>
     */
    public function query(): array
    {
        return array_filter([
            'q' => $this->q,
            'region' => array_map('strval', $this->regions),
            'equipment' => array_map('strval', $this->equipment),
            'difficulty' => array_map(fn (ExerciseDifficulty $d) => $d->value, $this->difficulties),
            'missing' => $this->missingMedia ? 'media' : null,
            'archived' => $this->archived ? '1' : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function isFiltered(): bool
    {
        return $this->query() !== [];
    }

    /**
     * The page of exercises in this slice, with what a gallery card shows.
     */
    public function exercises(): LengthAwarePaginator
    {
        return $this->filtered()
            ->with(['equipmentType', 'primaryMuscleGroups'])
            ->withCount(['partners as overrides_count' => fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereNotNull('partner_exercises.description')
                ->orWhereNotNull('partner_exercises.image')
                ->orWhereNotNull('partner_exercises.video'))])
            ->orderBy('workout_exercises.name')
            ->paginate(self::PER_PAGE)
            ->appends($this->query());
    }

    /**
     * Every facet value with how many exercises it matches under the other
     * filters, in display order; plus the counts behind the two toggles.
     *
     * @return array{
     *     region: list<array{value: int, label: string, count: int}>,
     *     equipment: list<array{value: int, label: string, count: int}>,
     *     difficulty: list<array{value: string, label: string, count: int}>,
     *     missing: int,
     *     archived: int,
     * }
     */
    public function facets(): array
    {
        $regionCounts = $this->filtered(except: 'region')
            ->groupBy('target_region_id')->selectRaw('target_region_id as value, count(*) as aggregate')
            ->pluck('aggregate', 'value');
        $equipmentCounts = $this->filtered(except: 'equipment')
            ->groupBy('equipment_type_id')->selectRaw('equipment_type_id as value, count(*) as aggregate')
            ->pluck('aggregate', 'value');
        $difficultyCounts = $this->filtered(except: 'difficulty')
            ->groupBy('difficulty')->selectRaw('difficulty as value, count(*) as aggregate')
            ->pluck('aggregate', 'value');

        return [
            'region' => TargetRegion::orderBy('display_order')->get()
                ->map(fn (TargetRegion $r) => ['value' => $r->id, 'label' => $r->name, 'count' => (int) ($regionCounts[$r->id] ?? 0)])
                ->all(),
            'equipment' => EquipmentType::orderBy('display_order')->get()
                ->map(fn (EquipmentType $e) => ['value' => $e->id, 'label' => $e->name, 'count' => (int) ($equipmentCounts[$e->id] ?? 0)])
                ->all(),
            'difficulty' => array_map(fn (ExerciseDifficulty $d) => [
                'value' => $d->value,
                'label' => ucfirst($d->value),
                'count' => (int) ($difficultyCounts[$d->value] ?? 0),
            ], ExerciseDifficulty::cases()),
            'missing' => $this->filtered(except: 'missing')->tap(fn (Builder $q) => $this->whereMissingMedia($q))->count(),
            'archived' => $this->filtered(except: 'archived')->whereNotNull('workout_exercises.archived_at')->count(),
        ];
    }

    /**
     * The exercises matching every filter except the one named.
     */
    private function filtered(?string $except = null): Builder
    {
        $query = Exercise::query()->search($this->q);

        if ($except !== 'archived') {
            $this->archived
                ? $query->whereNotNull('workout_exercises.archived_at')
                : $query->available();
        }
        if ($except !== 'region' && $this->regions !== []) {
            $query->whereIn('workout_exercises.target_region_id', $this->regions);
        }
        if ($except !== 'equipment' && $this->equipment !== []) {
            $query->whereIn('workout_exercises.equipment_type_id', $this->equipment);
        }
        if ($except !== 'difficulty' && $this->difficulties !== []) {
            $query->whereIn('workout_exercises.difficulty', array_map(fn ($d) => $d->value, $this->difficulties));
        }
        if ($except !== 'missing' && $this->missingMedia) {
            $this->whereMissingMedia($query);
        }

        return $query;
    }

    private function whereMissingMedia(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('workout_exercises.image')
            ->orWhereNull('workout_exercises.video')
            ->orWhereNull('workout_exercises.muscle_group_image'));
    }
}
