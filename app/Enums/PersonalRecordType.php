<?php

namespace App\Enums;

/**
 * The kinds of personal record a completed session can produce.
 *
 * Both describe the same set — a session's records are judged from one
 * record-setting set per exercise — so at most one of each is emitted per
 * exercise, and a set that beat both produces two records of one performance.
 * They stay separate cases because the client labels and formats them
 * differently: a weight record reads in kilograms, a reps record in reps.
 */
enum PersonalRecordType: string
{
    case Weight = 'weight';
    case Reps = 'reps';
}
