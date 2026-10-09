<?php

namespace App\Models;

use App\Enums\Entitlement;
use App\Enums\FreeAccessKind;
use App\Enums\PlanType;
use App\Enums\UnitSystem;
use App\Notifications\VerifyEmail;
use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** The role slugs that make an account staff rather than an app user. */
    public const STAFF_ROLES = ['admin', 'partner_admin'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo',
        'partner_id',
        'last_login_at',
        'onboarding_completed_at',
        'social_provider',
        'social_provider_id',
        'email_verified_at',
        'push_enabled',
        'notification_settings',
    ];

    /**
     * Defaults that mirror the column defaults, so a freshly built User answers
     * the same way as one read back from the table.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'push_enabled' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'push_enabled' => 'boolean',
            'notification_settings' => 'array',
            'grace_period_ends_at' => 'datetime',
            'free_access_kind' => FreeAccessKind::class,
        ];
    }

    /**
     * One Notification Setting (CONTEXT.md). The column holds only what the
     * user has changed, so a missing key — or a null column — is the default.
     * The Push Switch is not one of these: it is its own column and governs
     * push alone.
     */
    public function notificationSetting(string $key, bool $default): bool
    {
        return (bool) ($this->notification_settings[$key] ?? $default);
    }

    /**
     * Users whose Notification Setting $key is on — the query twin of
     * notificationSetting(): a missing key, or a null column, reads as
     * $default. The one place that rule is written for queries.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeNotificationSettingOn(Builder $query, string $key, bool $default): Builder
    {
        $column = 'users.notification_settings';

        return $query->where(fn (Builder $q) => $default
            ? $q->whereNull($column)->orWhereNull("{$column}->{$key}")->orWhere("{$column}->{$key}", true)
            : $q->where("{$column}->{$key}", true));
    }

    /**
     * Record one Notification Setting, keeping the others.
     */
    public function setNotificationSetting(string $key, bool $value): void
    {
        $this->notification_settings = [...($this->notification_settings ?? []), $key => $value];
    }

    /**
     * Send the custom branded email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    /**
     * Get the plans for the user.
     */
    public function plans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Get the active plan for the user.
     */
    public function activePlan(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Plan::class)->where('is_active', true)->where('type', PlanType::Routine);
    }

    /**
     * Get the active plan for the user.
     */
    public function activeProgram(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Plan::class)->where('is_active', true)->where('type', PlanType::Program);
    }

    /**
     * Get the workout sessions for the user.
     */
    public function workoutSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    /**
     * The Devices this user is signed in on that can receive push (ADR-0003).
     */
    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Get the partner that the user belongs to.
     */
    public function partner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the roles that belong to the user. The role_user timestamps say
     * since when (Admins::list()).
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Get the user's profile.
     */
    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * The unit system this user reads and writes measurements in. Users with
     * no profile yet fall back to metric, which is also the column default.
     */
    public function unitSystem(): UnitSystem
    {
        return $this->profile?->unit_system ?? UnitSystem::Metric;
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * @return Collection<int, Entitlement>
     */
    public function entitlements(): Collection
    {
        // Dark deploy: with enforcement off every user reports every entitlement,
        // so the app never shows a paywall (config/subscriptions.php).
        if (! config('subscriptions.enforced')) {
            return collect(Entitlement::cases());
        }

        $set = collect();

        if ($this->subscription?->isActive()) {
            $set = $set->merge($this->subscription->grantedEntitlements());
        }

        if ($this->partner?->isSponsoringMembers()) {
            $set->push(Entitlement::AppAccess);
        }

        if ($this->hasFreeAccess()) {
            $set->push(Entitlement::AppAccess);
        }

        return $set->unique()->values();
    }

    /**
     * Free access until a date, without payment: a Signup Trial or
     * Complimentary Access (CONTEXT.md). The date is grace_period_ends_at (a
     * name kept for now), the reason is free_access_kind. Either one grants
     * app access; scopeWithFreeAccess() is the same rule in SQL.
     */
    public function hasFreeAccess(): bool
    {
        return $this->grace_period_ends_at !== null && $this->grace_period_ends_at > now();
    }

    /**
     * Why the user has (or had) free access until grace_period_ends_at; null
     * when no date is set. A date with no recorded kind reads Complimentary:
     * every date set before the kind was recorded was an admin or test grant.
     */
    public function freeAccessKind(): ?FreeAccessKind
    {
        if ($this->grace_period_ends_at === null) {
            return null;
        }

        return $this->free_access_kind ?? FreeAccessKind::Complimentary;
    }

    /**
     * The Signup Trial (CONTEXT.md) is running. scopeOnSignupTrial() is the
     * same rule in SQL.
     */
    public function isOnSignupTrial(): bool
    {
        return $this->hasFreeAccess() && $this->freeAccessKind() === FreeAccessKind::SignupTrial;
    }

    /**
     * Complimentary Access (CONTEXT.md): an admin let this user in until a
     * date. scopeWithComplimentaryAccess() is the same rule in SQL.
     */
    public function hasComplimentaryAccess(): bool
    {
        return $this->hasFreeAccess() && $this->freeAccessKind() === FreeAccessKind::Complimentary;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithFreeAccess(Builder $query): Builder
    {
        return $query->where('users.grace_period_ends_at', '>', now());
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOnSignupTrial(Builder $query): Builder
    {
        return $query->withFreeAccess()->where('users.free_access_kind', FreeAccessKind::SignupTrial);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithComplimentaryAccess(Builder $query): Builder
    {
        return $query->withFreeAccess()->where(fn (Builder $q) => $q
            ->whereNull('users.free_access_kind')
            ->orWhere('users.free_access_kind', FreeAccessKind::Complimentary));
    }

    /**
     * Staff: an admin or partner-admin account, the complement of appUsers().
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }

    /**
     * People using the app: everyone but staff (admin and partner-admin
     * accounts), who the super-admin lists and counts leave out.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAppUsers(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('slug', self::STAFF_ROLES));
    }

    /**
     * Users with at least one Completed Session finished between $from and
     * $to — "active this week" on the Overview and the Partners list.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeTrainedBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        return $query->whereHas('workoutSessions', fn (Builder $sessions) => $sessions
            ->completed()
            ->whereBetween('completed_at', [$from, $to]));
    }

    /**
     * Users whose name or email contains $term, matched literally (a % or _
     * in the term is not a wildcard).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('users.name', 'like', $like)
            ->orWhere('users.email', 'like', $like));
    }

    public function hasEntitlement(Entitlement $e): bool
    {
        return $this->entitlements()->contains($e);
    }

    public function hasAppAccess(): bool
    {
        return $this->hasEntitlement(Entitlement::AppAccess);
    }

    /**
     * The Signup Trial (CONTEXT.md): the free days a new user gets when
     * onboarding completes — app access with no card and no store, until
     * grace_period_ends_at, so the paywall takes over when the date passes.
     * Once per account: a user who already had any free access (an earlier
     * trial, Complimentary Access, the launch grace — even one since ended)
     * never gets one. Returns whether a trial was started.
     */
    public function startSignupTrial(): bool
    {
        $days = (int) config('subscriptions.signup_trial_days', 0);

        if ($days <= 0 || $this->grace_period_ends_at !== null || $this->free_access_kind !== null) {
            return false;
        }

        $this->forceFill([
            'grace_period_ends_at' => now()->addDays($days),
            'free_access_kind' => FreeAccessKind::SignupTrial,
        ])->save();

        return true;
    }
}
