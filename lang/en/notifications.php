<?php

// Step 7 of the Unfinished Account ladder says the same thing whatever the
// user is stuck at; only the button differs.
$lastOne = [
    'subject' => 'Last one from us',
    'lead' => "We won't email again about this. If you'd like to start, everything is ready.",
];

return [

    // Inactivity Nudge copy, one entry per rung of the ladder (days without a
    // Completed Session). Keys must match config('notifications.inactivity.ladder').
    'inactivity' => [
        3 => [
            'title' => 'Ready when you are',
            'body' => "It's been 3 days. Your next workout is waiting.",
        ],
        7 => [
            'title' => 'A week off',
            'body' => 'Pick it back up today — even one session counts.',
        ],
        14 => [
            'title' => 'We miss you at the gym',
            'body' => "Two weeks is a long time. Let's get one in.",
        ],
    ],

    // Unfinished Account nudge copy, per rung of the ladder (days since
    // registration) and per what the user is stuck at. Keys must match
    // config('notifications.unfinished_account.ladder'). :partner is the
    // partner's name, or the app name for a user without one.
    'unfinished_account' => [
        1 => [
            'unverified' => [
                'subject' => 'Confirm your email for :partner',
                'lead' => "One tap and you're in. The link below verifies your address.",
            ],
            'not_onboarded' => [
                'subject' => 'Finish setting up :partner',
                'lead' => 'Two minutes of questions and your first plan is ready.',
            ],
        ],
        3 => [
            'unverified' => [
                'subject' => 'Still want to train with :partner?',
                'lead' => 'Your account is waiting on one thing — confirming your email.',
            ],
            'not_onboarded' => [
                'subject' => 'Your first plan is two minutes away',
                'lead' => "Tell us your goal and how often you train; we'll build the rest.",
            ],
        ],
        7 => [
            'unverified' => $lastOne,
            'not_onboarded' => $lastOne,
        ],
        'button' => [
            'unverified' => 'Verify email',
            'not_onboarded' => 'Open the app',
        ],
        'secondary' => [
            'unverified' => 'Then open the app to finish setting up',
        ],
    ],

];
