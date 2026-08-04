<?php

namespace App\Models;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
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
        'workout_duration_minutes',
        'unit_system',
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
            'unit_system' => UnitSystem::class,
            'age' => 'integer',
            'height' => 'integer',
            'weight' => 'decimal:2',
            'training_days_per_week' => 'integer',
            'workout_duration_minutes' => 'integer',
        ];
    }

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
