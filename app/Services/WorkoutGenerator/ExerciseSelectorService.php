<?php

namespace App\Services\WorkoutGenerator;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Collection;

class ExerciseSelectorService
{
    /**
     * Get available exercises based on filters
     */
    public function getAvailableExercises(array $filters = [], $partner = null): Collection
    {
        $query = Exercise::with(['muscleGroups', 'category', 'movementPattern', 'targetRegion', 'equipmentType', 'angle', 'trainingStyles'])
            ->available();

        if ($partner !== null) {
            $query->forPartner($partner);
        }

        // Filter by target regions if provided
        if (! empty($filters['target_regions'])) {
            $query->whereHas('targetRegion', function ($q) use ($filters) {
                $q->whereIn('code', $filters['target_regions']);
            });
        }

        // Filter by equipment types if provided
        if (! empty($filters['equipment_types'])) {
            $query->whereHas('equipmentType', function ($q) use ($filters) {
                $q->whereIn('code', $filters['equipment_types']);
            });
        }

        // Filter by movement patterns if provided
        if (! empty($filters['movement_patterns'])) {
            $query->whereHas('movementPattern', function ($q) use ($filters) {
                $q->whereIn('code', $filters['movement_patterns']);
            });
        }

        // Filter by angles if provided
        if (! empty($filters['angles'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('angle', function ($subQ) use ($filters) {
                    $subQ->whereIn('code', $filters['angles']);
                })->orWhereNull('angle_id'); // Include exercises without angles
            });
        }

        // Filter by training styles if provided
        // Include exercises without training styles when filtering for BODYBUILDING (default)
        if (! empty($filters['training_styles'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('trainingStyles', function ($subQ) use ($filters) {
                    $subQ->whereIn('code', $filters['training_styles']);
                });
            });
        }

        // Limit to reasonable number for AI context (avoid token limits)
        $limit = $filters['limit'] ?? 200;

        return $query->limit($limit)->get();
    }

    /**
     * Format exercises for AI context
     */
    public function formatForAI(Collection $exercises): array
    {
        return $exercises->map(function ($exercise) {
            $primaryMuscleGroups = $exercise->primaryMuscleGroups->pluck('name')->toArray();
            $secondaryMuscleGroups = $exercise->secondaryMuscleGroups->pluck('name')->toArray();

            return [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'description' => $exercise->description ?? '',
                'category' => $exercise->category?->name,
                'primary_muscle_groups' => $primaryMuscleGroups,
                'secondary_muscle_groups' => $secondaryMuscleGroups,
                'default_rest_sec' => $exercise->default_rest_sec,
                'movement_pattern_code' => $exercise->movementPattern?->code,
                'target_region_code' => $exercise->targetRegion?->code,
                'equipment_type_code' => $exercise->equipmentType?->code,
                'angle_code' => $exercise->angle?->code,
            ];
        })->toArray();
    }
}
