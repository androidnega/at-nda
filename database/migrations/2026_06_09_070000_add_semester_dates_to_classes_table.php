<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds calendar-based "where is this class in the semester" columns:
 *
 *   - semester_start_date     : first teaching week's anchor day
 *   - semester_end_date       : (optional) admin-set end; informational
 *                               cap. When unset we derive from
 *                               start + semester_weeks * 7 days.
 *   - current_week_override   : admin-set escape hatch. When non-null
 *                               this wins over calendar math, so an
 *                               admin can force "we are in week N"
 *                               regardless of dates.
 *
 * Without these, the student dashboard falls back to the existing
 * max(attendance_weeks.week_number) heuristic — so this migration
 * is purely additive and safe to ship.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            if (! Schema::hasColumn('classes', 'semester_start_date')) {
                $table->date('semester_start_date')
                    ->nullable()
                    ->after('semester_weeks');
            }
            if (! Schema::hasColumn('classes', 'semester_end_date')) {
                $table->date('semester_end_date')
                    ->nullable()
                    ->after('semester_start_date');
            }
            if (! Schema::hasColumn('classes', 'current_week_override')) {
                $table->unsignedSmallInteger('current_week_override')
                    ->nullable()
                    ->after('semester_end_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            foreach (['current_week_override', 'semester_end_date', 'semester_start_date'] as $col) {
                if (Schema::hasColumn('classes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
