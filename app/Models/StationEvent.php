<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An anonymous analytics event on a Station's public page: a Scan, an
 * exercise open, or a download-CTA click, with the app benefit that was
 * shown (see GLOSSARY: Scan). Append-only; carries no user identification.
 */
class StationEvent extends Model
{
    public const TYPES = ['scan', 'exercise_open', 'cta_click'];

    public const UPDATED_AT = null;

    protected $fillable = [
        'station_id',
        'exercise_id',
        'type',
        'benefit',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
