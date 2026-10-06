<?php

namespace App\Enums;

enum FitnessGoal: string
{
    use HasValueLabel;

    case FatLoss = 'fat_loss';
    case MuscleGain = 'muscle_gain';
    case Strength = 'strength';
    case GeneralFitness = 'general_fitness';
}
