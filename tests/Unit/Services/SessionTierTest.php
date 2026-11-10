<?php

namespace Tests\Unit\Services;

use App\Services\WorkoutGenerator\Slots\SessionTier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins the duration thresholds from issue #107: tier resolution stays
 * threshold-based (<40 / 40–60 / >60) as a safety net for off-menu values,
 * while the API accepts only the fixed menu.
 */
class SessionTierTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: SessionTier}>
     */
    public static function durations(): array
    {
        return [
            '30 minutes is express' => [30, SessionTier::Express],
            '39 minutes is express' => [39, SessionTier::Express],
            '40 minutes is standard' => [40, SessionTier::Standard],
            '45 minutes is standard' => [45, SessionTier::Standard],
            '60 minutes is standard' => [60, SessionTier::Standard],
            '61 minutes is extended' => [61, SessionTier::Extended],
            'legacy 75 minutes is extended' => [75, SessionTier::Extended],
            '90 minutes is extended' => [90, SessionTier::Extended],
            '120 minutes is extended' => [120, SessionTier::Extended],
        ];
    }

    #[DataProvider('durations')]
    public function test_duration_resolves_to_its_tier(int $minutes, SessionTier $expected): void
    {
        $this->assertSame($expected, SessionTier::fromDuration($minutes));
    }

    public function test_each_tier_admits_slots_up_to_its_tier_number(): void
    {
        $this->assertTrue(SessionTier::Express->includesTier(1));
        $this->assertFalse(SessionTier::Express->includesTier(2));
        $this->assertTrue(SessionTier::Standard->includesTier(2));
        $this->assertFalse(SessionTier::Standard->includesTier(3));
        $this->assertTrue(SessionTier::Extended->includesTier(3));
    }
}
