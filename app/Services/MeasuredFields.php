<?php

namespace App\Services;

use App\Enums\MeasurementKind;

/**
 * The single declaration of every database column that holds a measurement.
 *
 * This exists because the unit read path and the unit write path used to decide
 * a field's kind independently, at nine separate call sites, with nothing
 * checking that they agreed. When they disagreed the failure was silent: an
 * imperial user saw 135 lbs, saved 135, and stored 135 kg.
 *
 * Declaring the fact once means both paths derive from it, and the tests in
 * tests/Feature/MeasurementInvariantsTest.php can enumerate every measured
 * field and prove the two paths still agree.
 *
 * ADDING A MEASURED COLUMN: register it here. The completeness test will fail
 * until you do.
 */
class MeasuredFields
{
    /**
     * Every measured column, keyed by "table.column".
     *
     * Keyed centrally rather than declared on models because FormRequests
     * participate in this invariant and have no model to hang a declaration on.
     *
     * @var array<string, MeasurementKind>
     */
    private const FIELDS = [
        'workout_session_set_logs.weight' => MeasurementKind::TrainingWeight,
        'workout_template_exercises.target_weight' => MeasurementKind::TrainingWeight,
        'workout_session_exercises.target_weight' => MeasurementKind::TrainingWeight,
        'user_profiles.weight' => MeasurementKind::BodyWeight,
        'user_profiles.height' => MeasurementKind::Height,
    ];

    /**
     * Column names that carry a measurement wherever they appear. Used by the
     * architecture test to spot a request accepting a measured field without
     * converting it, and by the completeness test to spot an unregistered
     * column in the schema.
     *
     * @var array<int, string>
     */
    private const MEASURED_COLUMN_NAMES = ['weight', 'target_weight', 'height'];

    /**
     * @return array<string, MeasurementKind>
     */
    public static function all(): array
    {
        return self::FIELDS;
    }

    /**
     * @return array<int, string>
     */
    public static function measuredColumnNames(): array
    {
        return self::MEASURED_COLUMN_NAMES;
    }

    public static function kindFor(string $table, string $column): ?MeasurementKind
    {
        return self::FIELDS[$table.'.'.$column] ?? null;
    }
}
