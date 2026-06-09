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

    /*
    |--------------------------------------------------------------------------
    | Online rolling-code rotation
    |--------------------------------------------------------------------------
    |
    | How long an online-session attendance code stays valid before the next
    | one auto-mints. Default 120s (2 min). A small grace window keeps the
    | previously-current code valid for that many seconds AFTER it expires
    | so students mid-submit during a rotation don't get a 422.
    |
    */
    'online_code_rotation_seconds' => (int) env('ONLINE_CODE_ROTATION_SECONDS', 120),
    'online_code_grace_seconds'    => (int) env('ONLINE_CODE_GRACE_SECONDS', 10),

    /*
    | Online code character pool. 4 numeric digits today; widen if you need
    | more entropy. Keep length <= 8 to match the online_session_codes
    | column.
    */
    'online_code_length'  => (int) env('ONLINE_CODE_LENGTH', 4),
    'online_code_charset' => env('ONLINE_CODE_CHARSET', '0123456789'),

    /*
    |--------------------------------------------------------------------------
    | Risk scoring thresholds (online attendance)
    |--------------------------------------------------------------------------
    |
    | Drive AttendanceRiskService. Risk is informational ONLY — attendance
    | is always recorded regardless of score. Defaults match the spec.
    |
    */
    'risk_score_shared_fingerprint_session' => (int) env('RISK_SHARED_FP_SESSION', 50),
    'risk_score_fingerprint_many_accounts'  => (int) env('RISK_FP_MANY_ACCOUNTS', 40),
    'risk_score_shared_ip_session'          => (int) env('RISK_SHARED_IP_SESSION', 15),
    'risk_score_frequent_device_changes'    => (int) env('RISK_FREQUENT_DEVICE_CHANGE', 10),

    'risk_threshold_medium'  => (int) env('RISK_THRESHOLD_MEDIUM', 25),
    'risk_threshold_high'    => (int) env('RISK_THRESHOLD_HIGH', 50),

    // Rule 2: how many distinct students on one IP in one session triggers MEDIUM.
    'risk_ip_distinct_students_threshold' => (int) env('RISK_IP_DISTINCT_STUDENTS', 3),
    // Rule 3: how many distinct students on one fingerprint historically triggers HIGH.
    'risk_fingerprint_distinct_accounts_threshold' => (int) env('RISK_FP_DISTINCT_ACCOUNTS', 10),
    // Rule 4: distinct fingerprints per student over the lookback window to trigger LOW.
    'risk_student_device_switch_threshold' => (int) env('RISK_STUDENT_DEVICE_SWITCH', 4),
    'risk_student_device_switch_lookback_days' => (int) env('RISK_STUDENT_DEVICE_SWITCH_LOOKBACK', 30),

];
