<?php

namespace App\Enums;

enum PlanType: string
{
    use HasValueLabel;

    case Routine = 'routine';
    case Program = 'program';
}
