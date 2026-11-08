<?php

namespace App\Enums;

/**
 * How a customer dismissed a Join Prompt for a Partner (GLOSSARY: Join
 * Prompt): "Not interested" is a permanent decline; "Maybe later" — and any
 * implicit dismissal — is a 24-hour snooze, indefinitely repeatable. Stored
 * as join_prompt_dismissals.kind.
 */
enum JoinPromptDismissalKind: string
{
    case Declined = 'declined';
    case Snoozed = 'snoozed';
}
