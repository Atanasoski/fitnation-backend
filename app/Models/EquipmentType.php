<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentType extends Model
{
    use HasFactory;

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
