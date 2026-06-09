<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a per-class "semester length in weeks" column. The student
 * dashboard uses this as a consistent denominator across every
 * course card on that class — so instead of one course reading
 * "1/1 wks" and another "1/4 wks", every card reads "{present}/{X}"
 * where X is the same number for the class. Admins set the value
 * on the class create/edit form.
 *
 * Default of 12 covers the common short semester; admins are
 * expected to set the actual number per class.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }
        if (Schema::hasColumn('classes', 'semester_weeks')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedSmallInteger('semester_weeks')
                ->nullable()
                ->default(12)
                ->after('semester_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('classes') || ! Schema::hasColumn('classes', 'semester_weeks')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('semester_weeks');
        });
    }
};
