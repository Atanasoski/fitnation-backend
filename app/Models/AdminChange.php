<?php

namespace App\Models;

use App\Enums\AdminChangeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the admin change record: a super admin granted, extended or
 * ended Complimentary Access (`until`; null when ended), moved the user to
 * another partner (`from_partner_id` → `to_partner_id`), or cancelled a
 * Stuck Session (`workout_session_id`), with a reason. Written by
 * App\Services\Admin\UserChanges; shown as "Admin changes" on the user page.
 */
class AdminChange extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'kind',
        'until',
        'from_partner_id',
        'to_partner_id',
        'workout_session_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AdminChangeKind::class,
            'until' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    /** @return BelongsTo<Partner, $this> */
    public function fromPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'from_partner_id');
    }

    /** @return BelongsTo<Partner, $this> */
    public function toPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'to_partner_id');
    }

    /** @return BelongsTo<WorkoutSession, $this> */
    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }
}
