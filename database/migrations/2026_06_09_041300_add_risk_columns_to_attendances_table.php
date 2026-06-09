<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-blocking fraud-risk metadata on attendance rows.
 *
 * Attendance is ALWAYS recorded — these columns are only used by the
 * admin "Suspicious Attendance" panel to surface rows worth a human
 * review. AttendanceRiskService writes them; nothing else reads them
 * to allow / deny attendance.
 *
 * risk_level  : 'low' | 'medium' | 'high'  (NULL for in-person modes
 *               that haven't been scored)
 * risk_score  : integer 0-200 (clamped); thresholds defined in service.
 * risk_reasons: JSON array of short reason codes/labels (Rule 1 / 2 / 3 / 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'risk_score')) {
                $table->unsignedSmallInteger('risk_score')->nullable()->after('device_fingerprint');
            }
            if (! Schema::hasColumn('attendances', 'risk_level')) {
                $table->string('risk_level', 10)->nullable()->after('risk_score');
            }
            if (! Schema::hasColumn('attendances', 'risk_reasons')) {
                // Stored as JSON on databases that support it (MySQL/MariaDB,
                // SQLite via text). Cast as array in the Eloquent model.
                $table->json('risk_reasons')->nullable()->after('risk_level');
            }
        });

        // Index used by the admin "Suspicious Attendance" panel filter.
        if (Schema::hasColumn('attendances', 'risk_level')) {
            Schema::table('attendances', function (Blueprint $table) {
                // Wrapped in try/catch via the SchemaBuilder so re-running
                // the migration on a deploy that already has the index
                // doesn't blow up.
                try {
                    $table->index('risk_level', 'attendances_risk_level_idx');
                } catch (\Throwable $e) {
                    // Index already exists — ignore.
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            try {
                $table->dropIndex('attendances_risk_level_idx');
            } catch (\Throwable $e) {
                // Index didn't exist — ignore.
            }

            foreach (['risk_reasons', 'risk_level', 'risk_score'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
