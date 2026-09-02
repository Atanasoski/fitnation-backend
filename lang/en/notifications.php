<?php

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

];
