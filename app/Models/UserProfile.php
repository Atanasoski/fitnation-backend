<?php

namespace App\Models;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\SplitFocus;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'fitness_goal',
        'age',
        'gender',
        'height',
        'weight',
        'training_experience',
        'training_days_per_week',
        'split_focus',
        'workout_duration_minutes',
        'unit_system',
        'equipment_types',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fitness_goal' => FitnessGoal::class,
            'gender' => Gender::class,
            'training_experience' => TrainingExperience::class,
            'split_focus' => SplitFocus::class,
            'unit_system' => UnitSystem::class,
            'age' => 'integer',
            'height' => 'integer',
            'weight' => 'decimal:2',
            'training_days_per_week' => 'integer',
            'workout_duration_minutes' => 'integer',
            'equipment_types' => 'array',
        ];
    }

    /**
     * The member's Equipment Selection: EquipmentType codes, or null for
     * Full gym (ADR-0014's empty selection). An empty list is normalised to
     * null so there is one way to say Full gym, and Full gym is never stored
     * as a ticked list that would freeze out EquipmentTypes added later (#154).
     *
     * @return Attribute<list<string>|null, list<string>|null>
     */
    protected function equipmentTypes(): Attribute
    {
        return Attribute::make(
            set: fn (?array $value) => $value === null || $value === []
                ? null
                : json_encode(array_values(array_unique($value))),
        );
    }

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
