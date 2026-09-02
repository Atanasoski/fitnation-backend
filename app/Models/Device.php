<?php

namespace App\Models;

use Carbon\CarbonTimeZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One installation of the mobile app signed in as one user, reachable by push.
 *
 * A Device is bound to the Sanctum token it registered under and is deleted
 * with it (ADR-0003). Nothing else creates or moves a Device: go through
 * App\Services\Notifications\DeviceRegistration.
 */
class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'push_token',
        'platform',
        'timezone',
        'app_version',
        'build_profile',
        'device_name',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    /**
     * The timezone this phone is set to, or the app's home timezone when the
     * Device never reported one.
     */
    public function timezone(): CarbonTimeZone
    {
        return CarbonTimeZone::create($this->timezone ?? config('notifications.default_timezone'));
    }
}
