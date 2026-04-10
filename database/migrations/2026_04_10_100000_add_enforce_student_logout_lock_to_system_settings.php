<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'enforce_student_logout_lock')) {
                $table->boolean('enforce_student_logout_lock')
                    ->default(true)
                    ->after('allow_multiple_index_on_device');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('system_settings', 'enforce_student_logout_lock')) {
                $table->dropColumn('enforce_student_logout_lock');
            }
        });
    }
};
