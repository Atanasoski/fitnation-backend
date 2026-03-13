<?php

namespace App\Services;

use App\Enums\PlanType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PlanService
{
    /**
     * Return attributes array for creating a plan.
     * Centralizes type and owner (partner_id vs user_id) based on context.
     *
     * @param  array<string, mixed>  $validated  Validated request data (name, description, type, duration_weeks, is_active)
     * @param  'library'|'user'  $context
     * @return array<string, mixed>
     */
    public function createAttributes(array $validated, string $context, ?User $user = null): array
    {
        if ($context === 'library') {
            return [
                'partner_id' => Auth::id() ? Auth::user()->partner_id : null,
                'user_id' => null,
                'type' => PlanType::Program,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'duration_weeks' => $validated['duration_weeks'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ];
        }

        return [
            'user_id' => $user?->id,
            'partner_id' => null,
            'type' => PlanType::Program,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_weeks' => $validated['duration_weeks'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
        ];
    }
}
