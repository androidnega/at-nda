<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'allow_rep_attendance_deletion')) {
                $table->boolean('allow_rep_attendance_deletion')->default(false)->after('mail_from_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings') || ! Schema::hasColumn('system_settings', 'allow_rep_attendance_deletion')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('allow_rep_attendance_deletion');
        });
    }
};
