<?php

namespace App\Enums;

enum TrainingExperience: string
{
    use HasValueLabel;

    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
}
