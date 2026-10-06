<?php

namespace App\Enums;

enum TrainingExperience: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
