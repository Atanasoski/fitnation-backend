<?php

namespace Tests\Unit;

use App\Enums\MeasurementKind;
use App\Enums\UnitSystem;
use App\Services\UnitConversionService;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the pair the design rests on: toDisplay() and toStorage(). Every
 * rounding policy is reached through the MeasurementKind, never through a
 * kind-specific method, so the kind stays the only place a step is declared.
 */
class UnitConversionServiceTest extends TestCase
{
    private UnitConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitConversionService;
    }

    public function test_training_weight_metric_strips_trailing_zeros(): void
    {
        $this->assertSame(100, $this->service->toDisplay('100.00', MeasurementKind::TrainingWeight, UnitSystem::Metric));
        $this->assertSame(102.5, $this->service->toDisplay('102.50', MeasurementKind::TrainingWeight, UnitSystem::Metric));
    }

    public function test_training_weight_metric_passthrough_when_unit_system_is_null(): void
    {
        $this->assertSame(100, $this->service->toDisplay('100.00', MeasurementKind::TrainingWeight, null));
    }

    public function test_training_weight_imperial_rounds_to_nearest_five_lbs(): void
    {
        // 100kg * 2.2046226218 = 220.46226218 lbs -> rounds to 220
        $this->assertSame(220, $this->service->toDisplay(100, MeasurementKind::TrainingWeight, UnitSystem::Imperial));

        // 45kg * 2.2046226218 = 99.208017981 lbs -> rounds to 100
        $this->assertSame(100, $this->service->toDisplay(45, MeasurementKind::TrainingWeight, UnitSystem::Imperial));
    }

    public function test_training_weight_null_returns_null(): void
    {
        $this->assertNull($this->service->toDisplay(null, MeasurementKind::TrainingWeight, UnitSystem::Imperial));
        $this->assertNull($this->service->toDisplay(null, MeasurementKind::TrainingWeight, UnitSystem::Metric));
    }

    public function test_body_weight_metric_strips_trailing_zeros(): void
    {
        $this->assertSame(100, $this->service->toDisplay('100.00', MeasurementKind::BodyWeight, UnitSystem::Metric));
        $this->assertSame(102.5, $this->service->toDisplay('102.50', MeasurementKind::BodyWeight, UnitSystem::Metric));
    }

    public function test_body_weight_imperial_rounds_to_nearest_half_lb(): void
    {
        // 82.4kg * 2.2046226218 = 181.66090403632 lbs -> nearest 0.5 is 181.5
        $this->assertSame(181.5, $this->service->toDisplay(82.4, MeasurementKind::BodyWeight, UnitSystem::Imperial));
    }

    public function test_body_weight_null_returns_null(): void
    {
        $this->assertNull($this->service->toDisplay(null, MeasurementKind::BodyWeight, UnitSystem::Imperial));
    }

    /**
     * The two weight steps must stay distinct. If they ever converge — one
     * kind's step silently taking the other's — this is the test that says so.
     */
    public function test_training_and_body_weight_round_differently_at_the_same_input(): void
    {
        $kg = 82.4; // 181.66090403632 lbs

        $trainingWeight = $this->service->toDisplay($kg, MeasurementKind::TrainingWeight, UnitSystem::Imperial);
        $bodyWeight = $this->service->toDisplay($kg, MeasurementKind::BodyWeight, UnitSystem::Imperial);

        // If these ever report the same number, the two steps have converged and
        // one kind is reading the other kind's step.
        $this->assertSame(180, $trainingWeight, 'training weight must snap to the 5 lb step');
        $this->assertSame(181.5, $bodyWeight, 'body weight must snap to the 0.5 lb step');
    }

    public function test_height_metric_passthrough(): void
    {
        $this->assertSame(180, $this->service->toDisplay(180, MeasurementKind::Height, UnitSystem::Metric));
        $this->assertSame(180, $this->service->toDisplay('180', MeasurementKind::Height, null));
    }

    public function test_height_imperial_rounds_to_nearest_inch(): void
    {
        // 180cm * 0.3937007874 = 70.866141732 inches -> rounds to 71
        $this->assertSame(71, $this->service->toDisplay(180, MeasurementKind::Height, UnitSystem::Imperial));
    }

    public function test_height_null_returns_null(): void
    {
        $this->assertNull($this->service->toDisplay(null, MeasurementKind::Height, UnitSystem::Imperial));
    }

    public function test_body_weight_storage_and_display_roundtrip_within_step(): void
    {
        $originalLbs = 165.3;

        $storedKg = $this->service->toStorage($originalLbs, MeasurementKind::BodyWeight, UnitSystem::Imperial);
        $displayedLbs = $this->service->toDisplay($storedKg, MeasurementKind::BodyWeight, UnitSystem::Imperial);

        // Round-trip should land within the body-weight rounding policy's own step (0.5 lb).
        $this->assertEqualsWithDelta($originalLbs, $displayedLbs, 0.5);
    }

    public function test_height_storage_and_display_roundtrip_within_step(): void
    {
        $originalInches = 70.0;

        $storedCm = $this->service->toStorage($originalInches, MeasurementKind::Height, UnitSystem::Imperial);
        $displayedInches = $this->service->toDisplay($storedCm, MeasurementKind::Height, UnitSystem::Imperial);

        // Round-trip should land within the height rounding policy's own step (1 inch).
        $this->assertEqualsWithDelta($originalInches, $displayedInches, 1.0);
    }

    public function test_weight_storage_metric_passthrough(): void
    {
        $this->assertSame(73.2, $this->service->toStorage(73.2, MeasurementKind::BodyWeight, UnitSystem::Metric));
        $this->assertSame(73.2, $this->service->toStorage(73.2, MeasurementKind::TrainingWeight, UnitSystem::Metric));
    }

    public function test_height_storage_metric_rounds_to_int(): void
    {
        $this->assertSame(178, $this->service->toStorage(178.0, MeasurementKind::Height, UnitSystem::Metric));
    }

    public function test_training_weight_exact_half_step_tie_rounds_away_from_zero(): void
    {
        // kg chosen so kg * KG_TO_LBS == 102.5 exactly (halfway between 100 and 105 on the 5lb step).
        // PHP's round() is round-half-away-from-zero, so 102.5 / 5 = 20.5 rounds up to 21 -> 105.
        $kg = 102.5 / 2.2046226218;

        $this->assertSame(105, $this->service->toDisplay($kg, MeasurementKind::TrainingWeight, UnitSystem::Imperial));
    }

    public function test_body_weight_exact_half_step_tie_rounds_away_from_zero(): void
    {
        // kg chosen so kg * KG_TO_LBS == 100.25 exactly (halfway between 100 and 100.5 on the 0.5lb step).
        // 100.25 / 0.5 = 200.5 rounds up (away from zero) to 201 -> 100.5.
        $kg = 100.25 / 2.2046226218;

        $this->assertSame(100.5, $this->service->toDisplay($kg, MeasurementKind::BodyWeight, UnitSystem::Imperial));
    }
}
