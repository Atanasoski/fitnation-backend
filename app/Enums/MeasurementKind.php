<?php

namespace App\Enums;

/**
 * What kind of measurement a stored column holds. This is the domain fact that
 * both the read path (Resources) and the write path (FormRequests) derive from,
 * so the two can never disagree about a field.
 *
 * Each kind owns its own display policy: the imperial rounding step exists
 * because plates come in fixed increments, while body weight is tracked over
 * time and needs finer granularity. See CONTEXT.md.
 */
enum MeasurementKind: string
{
    case TrainingWeight = 'training_weight';
    case BodyWeight = 'body_weight';
    case Height = 'height';

    /**
     * The canonical storage unit for this kind. Nothing is ever persisted in
     * any other unit — see docs/adr/0001-convert-units-at-the-http-boundary.md.
     */
    public function canonicalUnit(): string
    {
        return $this === self::Height ? 'cm' : 'kg';
    }

    /**
     * The imperial unit this kind is displayed and submitted in.
     */
    public function imperialUnit(): string
    {
        return $this === self::Height ? 'in' : 'lbs';
    }

    /**
     * The step imperial display values snap to. Metric values are never
     * stepped — they pass through as stored.
     */
    public function imperialStep(): float
    {
        return match ($this) {
            self::TrainingWeight => 5.0,
            self::BodyWeight => 0.5,
            self::Height => 1.0,
        };
    }

    /**
     * Whether values of this kind are whole numbers once converted for display.
     */
    public function isWholeNumber(): bool
    {
        return $this === self::Height;
    }
}
