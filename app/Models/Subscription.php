<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Currently within the subscription window and not cancelled.
     *
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')
            ->where('starts_at', '<=', now())
            ->where(function (Builder $q): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeForPartnerUsers(Builder $query, int $partnerId): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) use ($partnerId): void {
            $userQuery->where('partner_id', $partnerId);
        });
    }

    /**
     * Derived state for UI and API (no persisted status column).
     *
     * @return 'active'|'cancelled'|'expired'|'upcoming'
     */
    public function derivedState(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'upcoming';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Human-readable label for {@see self::derivedState()}.
     */
    public function derivedStateLabel(): string
    {
        return (string) str($this->derivedState())->replace('_', ' ')->title();
    }

    /**
     * Whether a partner admin may cancel this row from the dashboard.
     */
    public function canBeCancelledByPartnerAdmin(): bool
    {
        if ($this->cancelled_at !== null) {
            return false;
        }

        $state = $this->derivedState();

        return $state === 'active' || $state === 'upcoming';
    }

    /**
     * Tailwind classes for a status pill (members table and subscription history).
     *
     * @param  'active'|'cancelled'|'expired'|'upcoming'|'none'  $state
     */
    public static function badgeClassesForDerivedState(string $state): string
    {
        return match ($state) {
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            'upcoming' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'cancelled' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-300',
            'expired' => 'bg-gray-100 text-gray-700 dark:bg-gray-900/40 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-900/40 dark:text-gray-300',
        };
    }

    /**
     * Tailwind classes for this subscription’s status pill.
     */
    public function statusBadgeClasses(): string
    {
        return self::badgeClassesForDerivedState($this->derivedState());
    }
}
