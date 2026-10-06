<?php

namespace App\Enums;

/**
 * A backed enum's display label read off its value: `general_fitness`
 * becomes "General fitness".
 */
trait HasValueLabel
{
    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
