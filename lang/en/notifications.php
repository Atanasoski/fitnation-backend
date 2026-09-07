<?php

// Step 7 of the Unfinished Account ladder says the same thing whatever the
// user is stuck at; only the button differs.
$lastOne = [
    'subject' => 'Last one from us',
    'lead' => "This is the last time we'll email you about this. Whenever you're ready, everything is set up and waiting.",
    'follow' => "One tap and you're training. We'd love to see you there.",
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

    // Unfinished Account nudge copy, per step of the ladder (days since
    // registration) and per what the user is stuck at. Keys must match
    // config('notifications.unfinished_account.ladder'). :partner is the
    // partner's name, or the app name for a user without one. `lead` is the
    // one line every channel carries (mail, push); `follow` is mail only.
    'unfinished_account' => [
        1 => [
            'unverified' => [
                'subject' => 'Confirm your email for :partner',
                'lead' => "You're one tap away from your first workout. Confirm your email and you're in.",
                'follow' => 'Your first plan will be ready the moment you are.',
            ],
            'not_onboarded' => [
                'subject' => 'Finish setting up :partner',
                'lead' => "You're almost there! Two quick minutes about your goals and your first plan is ready.",
                'follow' => 'No experience needed — every plan starts where you are and builds from there.',
            ],
        ],
        3 => [
            'unverified' => [
                'subject' => 'Still want to train with :partner?',
                'lead' => 'Your account is set up and waiting. Confirming your email is the only step left.',
                'follow' => 'Do it now and you could be training today.',
            ],
            'not_onboarded' => [
                'subject' => 'Your first plan is two minutes away',
                'lead' => "Tell us your goal and how often you can train, and we'll build a plan around your week.",
                'follow' => 'Most people finish in under two minutes — and then the hardest part is already done.',
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

    // Weekly Summary copy. `workouts` is a count phrase used in the subject and
    // the first block; `delta` values arrive already signed ("+2", "-1", "±0").
    // The trend line is chosen by the Mailable: `zero` is a week with nothing
    // logged after a week with something.
    'weekly_summary' => [
        'subject' => 'Your week: :workouts',
        'workouts' => '{0} no workouts|{1} 1 workout|[2,*] :count workouts',
        'workouts_delta' => ':delta vs last week',
        'volume' => ':volume :unit lifted',
        'volume_delta' => ':delta%',
        'time' => ':minutes min training',
        'trend' => [
            'up' => 'More than the week before. Keep the streak.',
            'same' => 'Same as last week. Consistency is the whole game.',
            'down' => "A lighter week. Next one's yours.",
            'zero' => 'Nothing logged this week. Your plan is where you left it.',
        ],
        'button' => 'Open the app',
        'unsubscribe' => 'Unsubscribe from weekly summaries',
    ],

];
