<?php

namespace App\Services\Notifications;

use Illuminate\Support\Collection;

/**
 * The rungs of a nudge ladder — days at which one is sent — and which rung a
 * count of days has reached. Configured as an ascending list of days.
 */
final class Ladder
{
    /** @var Collection<int, int> descending */
    private readonly Collection $steps;

    /**
     * @param  list<int>  $steps
     */
    public function __construct(array $steps)
    {
        $this->steps = collect($steps)->sortDesc()->values();
    }

    public static function fromConfig(string $key): self
    {
        return new self(config($key));
    }

    /**
     * The highest rung this many days has reached, or null below the first.
     */
    public function stepReached(int $days): ?int
    {
        return $this->steps->first(fn (int $step) => $days >= $step);
    }
}
