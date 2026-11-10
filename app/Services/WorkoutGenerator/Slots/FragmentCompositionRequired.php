<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use RuntimeException;

/**
 * The requested region combination matches no Blueprint; such a day is
 * composed from per-region Fragments (GLOSSARY: Fragment) — ticket #112.
 * Until that lands, the resolver refuses loudly instead of improvising.
 */
final class FragmentCompositionRequired extends RuntimeException
{
    /**
     * @param  list<string>  $regions
     */
    public static function for(array $regions): self
    {
        return new self(sprintf(
            'Region combination [%s] matches no blueprint; it requires fragment composition (#112).',
            implode(', ', $regions),
        ));
    }
}
