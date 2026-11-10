<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A target-region list names FULL_BODY alone or not at all (ADR-0010): a
 * full body is a full body, so combining it with other regions asks for a
 * day the engine cannot build.
 */
class FullBodyIsRequestedAlone implements ValidationRule
{
    private const FULL_BODY = 'FULL_BODY';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! in_array(self::FULL_BODY, $value, true)) {
            return;
        }

        if (array_diff($value, [self::FULL_BODY]) !== []) {
            $fail('Full Body is requested on its own: it cannot be combined with other target regions.');
        }
    }
}
