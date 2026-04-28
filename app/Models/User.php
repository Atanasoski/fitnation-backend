<?php

namespace App\Models;

use App\Enums\PlanType;
use App\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        ];
    }

    /**
     * Send the custom branded email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail());
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
     * Get the partner that the user belongs to.
     */
    public function partner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the roles that belong to the user.
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
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
}
