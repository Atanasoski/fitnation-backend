<?php

namespace App\Models;

use App\Enums\Entitlement;
use App\Enums\SubscriptionPeriodType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'partner_id',
        'product_id',
        'store',
        'status',
        'period_type',
        'price',
        'currency',
        'purchased_at',
        'expires_at',
        'cancelled_at',
        'environment',
        'last_event_at_ms',
    ];

    protected function casts(): array
    {
        return [
            'store' => SubscriptionStore::class,
            'status' => SubscriptionStatus::class,
            'period_type' => SubscriptionPeriodType::class,
            'price' => 'decimal:2',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_event_at_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereIn('status', self::accessGrantingStatuses())
            ->where('expires_at', '>', now());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->where('status', SubscriptionStatus::Active)
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    public function scopeAcquiredVia(Builder $query, Partner $partner): Builder
    {
        return $query->where('partner_id', $partner->id);
    }

    /**
     * Statuses that keep access for the remainder of the paid period. Only a
     * hard expiry (or a refund, which forces Expired + expires_at = now) ends
     * access early:
     *  - Cancelled: auto-renew off, the user paid through expires_at.
     *  - BillingIssue: the store is retrying the charge; locking out mid-retry
     *    would punish a user whose current period is already paid. If retries
     *    fail, the EXPIRATION webhook revokes access at period end.
     *  - Paused (Android): the pause takes effect at period end, so any
     *    remaining time is paid time.
     *
     * @return array<int, SubscriptionStatus>
     */
    public static function accessGrantingStatuses(): array
    {
        return [
            SubscriptionStatus::Active,
            SubscriptionStatus::Cancelled,
            SubscriptionStatus::BillingIssue,
            SubscriptionStatus::Paused,
        ];
    }

    public function isActive(): bool
    {
        if (! $this->expires_at || $this->expires_at <= now()) {
            return false;
        }

        return in_array($this->status, self::accessGrantingStatuses(), true);
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled
            && $this->expires_at
            && $this->expires_at > now();
    }

    public function isInTrial(): bool
    {
        return $this->period_type === SubscriptionPeriodType::Trial && $this->isActive();
    }

    /**
     * @return Collection<int, Entitlement>
     */
    public function grantedEntitlements(): Collection
    {
        $map = config('entitlements', []);

        return collect($map[$this->product_id] ?? []);
    }
}
