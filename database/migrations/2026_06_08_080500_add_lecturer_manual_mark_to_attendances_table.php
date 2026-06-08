<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track when a lecturer (not the student, not the class rep) inserted /
 * updated an attendance row via the online-class roll-call flow.
 *
 * Kept distinct from `marked_manually_by_id` because the existing column
 * references the students table (class reps are students). Mixing lecturer
 * IDs into that column would break the markedManuallyBy() relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'marked_manually_by_lecturer_id')) {
                $table->unsignedBigInteger('marked_manually_by_lecturer_id')
                    ->nullable()
                    ->after('marked_manually_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'marked_manually_by_lecturer_id')) {
                $table->dropColumn('marked_manually_by_lecturer_id');
            }
        });
    }
};
