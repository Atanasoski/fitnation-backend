<?php

namespace App\Enums;

enum PlanType: string
{
    case Routine = 'routine';
    case Program = 'program';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
