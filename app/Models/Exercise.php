<?php

namespace App\Models;

use App\Enums\ExerciseDifficulty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory;

    protected $table = 'workout_exercises';

    protected $fillable = [
        'category_id',
        'movement_pattern_id',
        'target_region_id',
        'equipment_type_id',
        'angle_id',
        'name',
        'description',
        'muscle_group_image',
        'image',
        'video',
        'default_rest_sec',
        'difficulty',
        'selection_priority',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => ExerciseDifficulty::class,
        ];
    }

    /**
     * Relationship: Exercise belongs to Category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relationship: Exercise belongs to MovementPattern (required)
     */
    public function movementPattern(): BelongsTo
    {
        return $this->belongsTo(MovementPattern::class);
    }

    /**
     * Relationship: Exercise belongs to TargetRegion (required)
     */
    public function targetRegion(): BelongsTo
    {
        return $this->belongsTo(TargetRegion::class);
    }

    /**
     * Relationship: Exercise belongs to EquipmentType (required)
     */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    /**
     * Relationship: Exercise belongs to Angle (nullable)
     */
    public function angle(): BelongsTo
    {
        return $this->belongsTo(Angle::class);
    }

    /**
     * Relationship: Exercise has many WorkoutTemplateExercises
     */
    public function workoutTemplateExercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class);
    }

    /**
     * Relationship: Exercise has many SetLogs
     */
    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }

    /**
     * Relationship: Exercise has many MuscleGroups (many-to-many)
     */
    public function muscleGroups(): BelongsToMany
    {
        return $this->belongsToMany(MuscleGroup::class, 'exercise_muscle_group')
            ->withPivot('is_primary');
    }

    /**
     * Relationship: Exercise's primary muscle groups
     */
    public function primaryMuscleGroups(): BelongsToMany
    {
        return $this->muscleGroups()->wherePivot('is_primary', true);
    }

    /**
     * Relationship: Exercise's secondary muscle groups
     */
    public function secondaryMuscleGroups(): BelongsToMany
    {
        return $this->muscleGroups()->wherePivot('is_primary', false);
    }

    /**
     * Relationship: Exercise belongs to many Partners (many-to-many)
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'partner_exercises')
            ->withPivot(['description', 'image', 'video'])
            ->withTimestamps();
    }

    /**
     * Relationship: Exercise belongs to many TrainingStyles (many-to-many)
     */
    public function trainingStyles(): BelongsToMany
    {
        return $this->belongsToMany(TrainingStyle::class, 'exercise_training_style');
    }

    /**
     * Scope: Get exercises for a specific partner
     */
    public function scopeForPartner($query, Partner $partner)
    {
        return $query->whereHas('partners', function ($q) use ($partner) {
            $q->where('partners.id', $partner->id);
        });
    }

    /**
     * Tokenize a search term into a list of lowercase, alphanumeric tokens.
     *
     * Strips characters that are unsafe for FULLTEXT boolean syntax and trims
     * whitespace. Returns an empty array for blank input so callers can short-circuit.
     *
     * @return array<int, string>
     */
    public static function tokenize(?string $term): array
    {
        if ($term === null) {
            return [];
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $term) ?? '';
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('mb_strtolower', explode(' ', $normalized)),
            fn (string $token) => $token !== ''
        ));
    }

    /**
     * Scope: Filter exercises whose name contains every token in $term (AND).
     *
     * Uses LIKE per-token rather than FULLTEXT MATCH so short tokens and
     * stopwords still match. FULLTEXT is reserved for relevance ordering
     * in the controller.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $tokens = self::tokenize($term);

        if ($tokens === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->where('workout_exercises.name', 'like', '%'.$token.'%');
            }
        });
    }

    /**
     * Get the description for a partner (pivot override or exercise default)
     */
    public function getDescription(?Partner $partner = null): ?string
    {
        if ($partner) {
            // Check if partners relationship is loaded and get pivot from there
            if ($this->relationLoaded('partners') && $this->partners->isNotEmpty()) {
                $partnerRelation = $this->partners->firstWhere('id', $partner->id);
                if ($partnerRelation && $partnerRelation->pivot) {
                    return $partnerRelation->pivot->description ?? $this->description;
                }
            }

            // Fallback: check if this was loaded via partner->exercises (pivot on the model itself)
            // Only use this if the pivot has a partner_id matching the requested partner
            if ($this->pivot && isset($this->pivot->partner_id) && $this->pivot->partner_id === $partner->id) {
                return $this->pivot->description ?? $this->description;
            }
        }

        return $this->description;
    }

    /**
     * Get the image for a partner (pivot override or exercise default)
     */
    public function getImage(?Partner $partner = null): ?string
    {
        if ($partner) {
            // Check if partners relationship is loaded and get pivot from there
            if ($this->relationLoaded('partners') && $this->partners->isNotEmpty()) {
                $partnerRelation = $this->partners->firstWhere('id', $partner->id);
                if ($partnerRelation && $partnerRelation->pivot) {
                    return $partnerRelation->pivot->image ?? $this->image;
                }
            }

            // Fallback: check if this was loaded via partner->exercises (pivot on the model itself)
            // Only use this if the pivot has a partner_id matching the requested partner
            if ($this->pivot && isset($this->pivot->partner_id) && $this->pivot->partner_id === $partner->id) {
                return $this->pivot->image ?? $this->image;
            }
        }

        return $this->image;
    }

    /**
     * Get the video for a partner (pivot override or exercise default)
     */
    public function getVideo(?Partner $partner = null): ?string
    {
        if ($partner) {
            // Check if partners relationship is loaded and get pivot from there
            if ($this->relationLoaded('partners') && $this->partners->isNotEmpty()) {
                $partnerRelation = $this->partners->firstWhere('id', $partner->id);
                if ($partnerRelation && $partnerRelation->pivot) {
                    return $partnerRelation->pivot->video ?? $this->video;
                }
            }

            // Fallback: check if this was loaded via partner->exercises (pivot on the model itself)
            // Only use this if the pivot has a partner_id matching the requested partner
            if ($this->pivot && isset($this->pivot->partner_id) && $this->pivot->partner_id === $partner->id) {
                return $this->pivot->video ?? $this->video;
            }
        }

        return $this->video;
    }
}
