<?php

namespace App\Models;

use App\Enums\JoinPromptDismissalKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A customer's dismissal of the Join Prompt for one Partner (GLOSSARY: Join
 * Prompt; ADR 0006): either a permanent decline ("Not interested") or a
 * 24-hour snooze ("Maybe later", or dismissing without choosing). One row per
 * customer×Partner, upserted on repeat — a fresh snooze resets the window, a
 * decline overwrites a snooze, and nothing ever un-declines.
 */
class JoinPromptDismissal extends Model
{
    use HasFactory;

    public const SNOOZE_HOURS = 24;

    protected $fillable = [
        'user_id',
        'partner_id',
        'kind',
        'snoozed_until',
    ];

    protected function casts(): array
    {
        return [
            'kind' => JoinPromptDismissalKind::class,
            'snoozed_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Record a dismissal, upserting the customer×Partner row. A decline is
     * terminal: a later snooze is accepted and ignored, never un-declining.
     * A fresh snooze always resets the 24-hour window.
     */
    public static function record(int $userId, int $partnerId, JoinPromptDismissalKind $kind): void
    {
        $keys = ['user_id' => $userId, 'partner_id' => $partnerId];

        try {
            static::firstOrNew($keys)->apply($kind);
        } catch (UniqueConstraintViolationException) {
            // A concurrent dismissal (double-tap) created the row between the
            // lookup and the insert; apply onto the row that won.
            static::where($keys)->firstOrFail()->apply($kind);
        }
    }

    private function apply(JoinPromptDismissalKind $kind): void
    {
        // Declined is terminal (ADR 0006): nothing ever un-declines.
        if ($this->kind !== JoinPromptDismissalKind::Declined) {
            $this->kind = $kind;
            $this->snoozed_until = $kind === JoinPromptDismissalKind::Snoozed
                ? now()->addHours(self::SNOOZE_HOURS)
                : null;
        }

        $this->save();
    }

    /**
     * Dismissals still silencing the Join Prompt: declines forever, snoozes
     * until their window expires. An expired snooze row stays behind but no
     * longer matches.
     */
    public function scopeSilencing(Builder $query): Builder
    {
        return $query->where(fn (Builder $silencing) => $silencing
            ->where('kind', JoinPromptDismissalKind::Declined)
            ->orWhere('snoozed_until', '>', now()));
    }
}
