<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'require_profile_image_on_onboarding')) {
                $table->boolean('require_profile_image_on_onboarding')
                    ->default(true)
                    ->after('require_password_on_first_login');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('system_settings', 'require_profile_image_on_onboarding')) {
                $table->dropColumn('require_profile_image_on_onboarding');
            }
        });
    }
};
