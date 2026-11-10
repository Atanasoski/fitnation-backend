<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentType extends Model
{
    use HasFactory;

    /**
     * Equipment whose exercises are FUNCTIONAL training. The catalogue
     * seeders tag these exercises FUNCTIONAL, and the generator drops a
     * BODYBUILDING-only style default when a member's equipment selection
     * includes any of them (the member meant the equipment).
     *
     * @var list<string>
     */
    public const FUNCTIONAL_CODES = ['TRX', 'LANDMINE', 'KETTLEBELL', 'BAND', 'MEDICINE_BALL'];

    protected $fillable = [
        'code',
        'name',
        'display_order',
        'supports_added_weight',
    ];

    protected $casts = [
        'supports_added_weight' => 'boolean',
    ];

    /**
     * Relationship: EquipmentType has many Exercises
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
