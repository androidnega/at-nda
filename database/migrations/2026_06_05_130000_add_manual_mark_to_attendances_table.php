<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'marked_manually_by_id')) {
                // Class rep who recorded this mark on a student's behalf
                // (links back to the students table). Null on normal self-marks.
                $table->unsignedBigInteger('marked_manually_by_id')->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('attendances', 'manual_reason')) {
                $table->string('manual_reason', 500)->nullable()->after('marked_manually_by_id');
            }
            if (! Schema::hasColumn('attendances', 'marked_manually_at')) {
                $table->timestamp('marked_manually_at')->nullable()->after('manual_reason');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            foreach (['marked_manually_at', 'manual_reason', 'marked_manually_by_id'] as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
