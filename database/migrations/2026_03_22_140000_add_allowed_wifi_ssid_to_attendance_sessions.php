<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'allowed_wifi_ssid')) {
                $table->string('allowed_wifi_ssid', 128)->nullable()->after('mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'allowed_wifi_ssid')) {
                $table->dropColumn('allowed_wifi_ssid');
            }
        });
    }
};
