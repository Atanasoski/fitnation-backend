<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical spot on a Partner's gym floor (e.g. "Cable Crossover") carrying a
 * printed QR code and a curated, ordered list of Exercises (see GLOSSARY).
 *
 * The short code is the only identifier that ever gets printed (ADR-0005):
 * it is generated once on creation and must never be regenerated or recycled,
 * which is why stations soft-delete only.
 */
class Station extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'partner_id',
        'name',
    ];

    /**
     * Lowercase alphanumerics without the lookalikes (0/o, 1/i/l), so a code
     * read off a printed sticker can be typed by hand without ambiguity.
     */
    private const SHORT_CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const SHORT_CODE_LENGTH = 8;

    protected static function booted(): void
    {
        static::creating(function (Station $station) {
            $station->short_code ??= self::generateShortCode();
        });
    }

    /**
     * A fresh unique short code. Uniqueness is checked against trashed
     * stations too: a deleted station's printed stickers still resolve to its
     * code, so the code is never free to hand out again.
     */
    public static function generateShortCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::SHORT_CODE_LENGTH; $i++) {
                $code .= self::SHORT_CODE_ALPHABET[random_int(0, strlen(self::SHORT_CODE_ALPHABET) - 1)];
            }
        } while (self::withTrashed()->where('short_code', $code)->exists());

        return $code;
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * The curated list, in the order the admin arranged it.
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'station_exercise')
            ->withPivot('display_order')
            ->withTimestamps()
            ->orderBy('station_exercise.display_order');
    }

    /**
     * Anonymous analytics events recorded against this station's public page.
     */
    public function events(): HasMany
    {
        return $this->hasMany(StationEvent::class);
    }
}
