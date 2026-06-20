<?php

namespace App\Models;

use App\Enums\PartnerPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'is_active',
        'plan',
        'plan_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'plan' => PartnerPlan::class,
            'plan_expires_at' => 'datetime',
        ];
    }

    public function isSponsoringMembers(): bool
    {
        return $this->plan === PartnerPlan::Sponsor
            && ($this->plan_expires_at === null || $this->plan_expires_at > now());
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the identity for the partner.
     */
    public function identity(): HasOne
    {
        return $this->hasOne(PartnerIdentity::class);
    }

    /**
     * Get the users for the partner.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the user invitations for the partner.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    /**
     * Get the plans for the partner (library plans).
     */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Relationship: Partner belongs to many Exercises (many-to-many)
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'partner_exercises', 'partner_id', 'exercise_id')
            ->withPivot(['description', 'image', 'video'])
            ->withTimestamps();
    }

    /**
     * Sync all exercises to this partner.
     * This creates pivot rows with null override values, which will fall back to exercise defaults.
     */
    public function syncDefaultExercises(): void
    {
        $defaultExercises = Exercise::pluck('id');

        $pivotData = $defaultExercises->mapWithKeys(function ($exerciseId) {
            return [$exerciseId => [
                'description' => null,
                'image' => null,
                'video' => null,
            ]];
        })->toArray();

        $this->exercises()->syncWithoutDetaching($pivotData);
    }

    /**
     * Check if the partner can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->users()->count() === 0;
    }
}
