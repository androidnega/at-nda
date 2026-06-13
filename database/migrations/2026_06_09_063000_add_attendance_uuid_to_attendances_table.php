<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a client-generated idempotency key (`attendance_uuid`) to every
 * attendance row so the offline-first mobile pipeline can safely replay
 * batches without producing duplicate marks.
 *
 * - Column is nullable so legacy rows (and the web flow which has no
 *   uuid yet) keep working.
 * - Unique-when-not-null index avoids the NULL collision MySQL would
 *   otherwise enforce.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }
        if (Schema::hasColumn('attendances', 'attendance_uuid')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $t): void {
            $t->string('attendance_uuid', 64)->nullable()->after('id');
        });

        // Unique index lets the server reject duplicate inserts at the DB
        // layer even if two API workers race for the same row.
        Schema::table('attendances', function (Blueprint $t): void {
            $t->unique('attendance_uuid', 'attendances_attendance_uuid_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }
        if (Schema::hasColumn('attendances', 'attendance_uuid')) {
            Schema::table('attendances', function (Blueprint $t): void {
                try {
                    $t->dropUnique('attendances_attendance_uuid_unique');
                } catch (\Throwable $e) {
                }
                $t->dropColumn('attendance_uuid');
            });
        }
    }
};
