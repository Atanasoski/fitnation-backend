<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentType extends Model
{
    use HasFactory;

    /**
     * Equipment whose exercises the catalogue seeders tag FUNCTIONAL. Only
     * the seeders read it; the generator filters on Training Style alone
     * (ADR-0014).
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

    /**
     * Scope: equipment with at least one available Exercise of the given
     * Training Style in the Partner Library (the whole catalogue when there
     * is no Partner), the set offered for generation (ADR-0014).
     */
    public function scopeWithTrainingStyle(Builder $query, string $trainingStyle, ?Partner $partner = null): Builder
    {
        return $query->whereHas('exercises', function (Builder $exercises) use ($trainingStyle, $partner) {
            $exercises->available()
                ->whereHas('trainingStyles', fn ($q) => $q->where('training_styles.code', $trainingStyle))
                ->when($partner, fn (Builder $q) => $q->forPartner($partner));
        });
    }
}
