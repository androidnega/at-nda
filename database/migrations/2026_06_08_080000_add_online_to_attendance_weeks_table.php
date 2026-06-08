<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online-class marker for an attendance week.
 *
 * When set, the lecturer rolled call manually because the meeting happened
 * over Zoom / Meet / Teams (no GPS, no QR). The flag lets the UI show an
 * "Online" badge and lets reports tell remote weeks from in-person ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_weeks')) {
            return;
        }

        Schema::table('attendance_weeks', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_weeks', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('cancellation_note');
            }
            if (! Schema::hasColumn('attendance_weeks', 'online_platform')) {
                $table->string('online_platform', 60)->nullable()->after('is_online');
            }
            if (! Schema::hasColumn('attendance_weeks', 'online_note')) {
                $table->string('online_note', 500)->nullable()->after('online_platform');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_weeks')) {
            return;
        }

        Schema::table('attendance_weeks', function (Blueprint $table) {
            foreach (['online_note', 'online_platform', 'is_online'] as $col) {
                if (Schema::hasColumn('attendance_weeks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
