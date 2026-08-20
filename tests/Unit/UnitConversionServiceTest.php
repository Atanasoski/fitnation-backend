<?php

namespace Tests\Unit;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;
use PHPUnit\Framework\TestCase;

class UnitConversionServiceTest extends TestCase
{
    private UnitConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitConversionService;
    }

    public function test_format_training_weight_metric_strips_trailing_zeros(): void
    {
        $this->assertSame(100, $this->service->formatTrainingWeight('100.00', UnitSystem::Metric));
        $this->assertSame(102.5, $this->service->formatTrainingWeight('102.50', UnitSystem::Metric));
    }

    public function test_format_training_weight_metric_passthrough_when_unit_system_is_null(): void
    {
        $this->assertSame(100, $this->service->formatTrainingWeight('100.00', null));
    }

    public function test_format_training_weight_imperial_rounds_to_nearest_five_lbs(): void
    {
        // 100kg * 2.2046226218 = 220.46226218 lbs -> rounds to 220
        $this->assertSame(220, $this->service->formatTrainingWeight(100, UnitSystem::Imperial));

        // 45kg * 2.2046226218 = 99.208017981 lbs -> rounds to 100
        $this->assertSame(100, $this->service->formatTrainingWeight(45, UnitSystem::Imperial));
    }

    public function test_format_training_weight_null_returns_null(): void
    {
        $this->assertNull($this->service->formatTrainingWeight(null, UnitSystem::Imperial));
        $this->assertNull($this->service->formatTrainingWeight(null, UnitSystem::Metric));
    }

    public function test_format_body_weight_metric_strips_trailing_zeros(): void
    {
        $this->assertSame(100, $this->service->formatBodyWeight('100.00', UnitSystem::Metric));
        $this->assertSame(102.5, $this->service->formatBodyWeight('102.50', UnitSystem::Metric));
    }

    public function test_format_body_weight_imperial_rounds_to_nearest_half_lb(): void
    {
        // 82.4kg * 2.2046226218 = 181.66090403632 lbs
        // body-weight (0.5 step) -> 181.5, distinct from training-weight (5 step) -> 180
        $this->assertSame(181.5, $this->service->formatBodyWeight(82.4, UnitSystem::Imperial));
        $this->assertSame(180, $this->service->formatTrainingWeight(82.4, UnitSystem::Imperial));
    }

    public function test_format_body_weight_null_returns_null(): void
    {
        $this->assertNull($this->service->formatBodyWeight(null, UnitSystem::Imperial));
    }

    public function test_format_height_metric_passthrough(): void
    {
        $this->assertSame(180, $this->service->formatHeight(180, UnitSystem::Metric));
        $this->assertSame(180, $this->service->formatHeight('180', null));
    }

    public function test_format_height_imperial_rounds_to_nearest_inch(): void
    {
        // 180cm * 0.3937007874 = 70.866141732 inches -> rounds to 71
        $this->assertSame(71, $this->service->formatHeight(180, UnitSystem::Imperial));
    }

    public function test_format_height_null_returns_null(): void
    {
        $this->assertNull($this->service->formatHeight(null, UnitSystem::Imperial));
    }

    public function test_to_kg_and_format_body_weight_roundtrip_within_step(): void
    {
        $originalLbs = 165.3;

        $storedKg = $this->service->toKg($originalLbs, UnitSystem::Imperial);
        $displayedLbs = $this->service->formatBodyWeight($storedKg, UnitSystem::Imperial);

        // Round-trip should land within the body-weight rounding policy's own step (0.5 lb).
        $this->assertEqualsWithDelta($originalLbs, $displayedLbs, 0.5);
    }

    public function test_to_cm_and_format_height_roundtrip_within_step(): void
    {
        $originalInches = 70.0;

        $storedCm = $this->service->toCm($originalInches, UnitSystem::Imperial);
        $displayedInches = $this->service->formatHeight($storedCm, UnitSystem::Imperial);

        // Round-trip should land within the height rounding policy's own step (1 inch).
        $this->assertEqualsWithDelta($originalInches, $displayedInches, 1.0);
    }

    public function test_to_kg_metric_passthrough(): void
    {
        $this->assertSame(73.2, $this->service->toKg(73.2, UnitSystem::Metric));
    }

    public function test_to_cm_metric_rounds_to_int(): void
    {
        $this->assertSame(178, $this->service->toCm(178.0, UnitSystem::Metric));
    }

    public function test_format_training_weight_exact_half_step_tie_rounds_away_from_zero(): void
    {
        // kg chosen so kg * KG_TO_LBS == 102.5 exactly (halfway between 100 and 105 on the 5lb step).
        // PHP's round() is round-half-away-from-zero, so 102.5 / 5 = 20.5 rounds up to 21 -> 105.
        $kg = 102.5 / 2.2046226218;

        $this->assertSame(105, $this->service->formatTrainingWeight($kg, UnitSystem::Imperial));
    }

    public function test_format_body_weight_exact_half_step_tie_rounds_away_from_zero(): void
    {
        // kg chosen so kg * KG_TO_LBS == 100.25 exactly (halfway between 100 and 100.5 on the 0.5lb step).
        // 100.25 / 0.5 = 200.5 rounds up (away from zero) to 201 -> 100.5.
        $kg = 100.25 / 2.2046226218;

        $this->assertSame(100.5, $this->service->formatBodyWeight($kg, UnitSystem::Imperial));
    }
}
