<?php

namespace App\Models;

use App\Enums\PartnerKind;
use App\Enums\PartnerPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partner extends Model
{
    use HasFactory;

    public const HOUSE_CANNOT_BE_DEACTIVATED = 'The House Partner cannot be deactivated.';

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

    /**
     * The House Partner's id: Fit Nation itself, where everyone who joins
     * without a gym belongs.
     */
    public static function houseId(): int
    {
        return (int) config('partners.house_partner_id');
    }

    public function isHouse(): bool
    {
        return $this->getKey() === self::houseId();
    }

    /**
     * Every signup without a gym lands on the House Partner; deactivating it
     * would turn every one of them away. Both the Partners page and the edit
     * form check this before writing is_active = false.
     */
    public function canBeDeactivated(): bool
    {
        return ! $this->isHouse();
    }

    /**
     * House if this is the configured House Partner, Sponsoring if it is on
     * the sponsor plan (whether or not the sponsorship has run out), else
     * plain.
     */
    public function kind(): PartnerKind
    {
        return match (true) {
            $this->isHouse() => PartnerKind::House,
            $this->plan === PartnerPlan::Sponsor => PartnerKind::Sponsoring,
            default => PartnerKind::Plain,
        };
    }

    /**
     * A Sponsoring Partner whose sponsorship has not run out: its members have
     * access without a subscription. scopeSponsoringMembers() is the same rule
     * in SQL; keep the two together.
     */
    /**
     * Sponsoring Partners whose sponsorship runs out after now and within
     * $days days.
     */
    public function scopeSponsorshipExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->where('plan', PartnerPlan::Sponsor)
            ->where('plan_expires_at', '>', now())
            ->where('plan_expires_at', '<=', now()->addDays($days));
    }

    public function isSponsoringMembers(): bool
    {
        return $this->plan === PartnerPlan::Sponsor
            && ($this->plan_expires_at === null || $this->plan_expires_at > now());
    }

    /**
     * @param  Builder<Partner>  $query
     * @return Builder<Partner>
     */
    public function scopeSponsoringMembers(Builder $query): Builder
    {
        return $query
            ->where('partners.plan', PartnerPlan::Sponsor)
            ->where(fn (Builder $q) => $q
                ->whereNull('partners.plan_expires_at')
                ->orWhere('partners.plan_expires_at', '>', now()));
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
