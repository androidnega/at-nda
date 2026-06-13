<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention sweep for attendance_late_unrecorded. Pending rows are
// never touched; approved/denied rows older than 60 days are deleted
// because the decision is already captured in audit_logs and (for
// approved rows) on the resulting attendances row.
// See POST_IMPLEMENTATION_ARCHITECTURE_AUDIT §P1.5.
Schedule::command('attendance:late:prune --days=60')
    ->dailyAt('03:15')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->onOneServer();
