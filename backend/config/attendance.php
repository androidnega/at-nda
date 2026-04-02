<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Missed session warnings (Flutter / mobile)
    |--------------------------------------------------------------------------
    |
    | A session counts as "missed" when it has ended and the student has no
    | attendance row for that session. Warnings list courses where the student
    | has at least this many missed ended sessions (same class as course).
    |
    */
    'missed_warning_min_sessions' => (int) env('MISSED_WARNING_MIN_SESSIONS', 2),

    /*
    | Only consider sessions that ended within this many days (null = all time).
    | Example: 30 = "recent" missed sessions in the last month.
    |
    */
    'missed_warning_lookback_days' => env('MISSED_WARNING_LOOKBACK_DAYS') !== null && env('MISSED_WARNING_LOOKBACK_DAYS') !== ''
        ? (int) env('MISSED_WARNING_LOOKBACK_DAYS')
        : null,

];
