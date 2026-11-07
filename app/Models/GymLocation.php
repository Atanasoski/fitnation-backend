<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A geographic point with a radius marking where a Partner's gym is; a Partner
 * may have several (GLOSSARY: Gym Location). A customer inside any of them is
 * "near" the Partner, which is what makes the Join Prompt fire — but only once
 * the Partner's geo_prompt_enabled switch is on.
 */
class GymLocation extends Model
{
    use HasFactory;

    public const DEFAULT_RADIUS_M = 150;

    protected $fillable = [
        'label',
        'latitude',
        'longitude',
        'radius_m',
    ];

    protected $attributes = [
        'radius_m' => self::DEFAULT_RADIUS_M,
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
