<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'mobile_app_theme_seed')) {
                $table->string('mobile_app_theme_seed', 32)->default('teal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('system_settings', 'mobile_app_theme_seed')) {
                $table->dropColumn('mobile_app_theme_seed');
            }
        });
    }
};
