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

        if (! Schema::hasColumn('system_settings', 'auth_hero_image_path')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('auth_hero_image_path', 512)->nullable()->after('mobile_app_theme_seed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'auth_hero_image_path')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->dropColumn('auth_hero_image_path');
            });
        }
    }
};
