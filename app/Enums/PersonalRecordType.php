<?php

namespace App\Enums;

/**
 * The kinds of personal record a completed session can produce.
 *
 * The two are detected independently of one another, which is why they are
 * separate cases rather than one record carrying both numbers — see
 * PersonalRecords for what that costs.
 */
enum PersonalRecordType: string
{
    case Weight = 'weight';
    case Reps = 'reps';
}
