<?php

namespace App\Enums;

enum FitnessGoal: string
{
    case FatLoss = 'fat_loss';
    case MuscleGain = 'muscle_gain';
    case Strength = 'strength';
    case GeneralFitness = 'general_fitness';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
