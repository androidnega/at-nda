<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('logged_whatsapp_messages');
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('logged_sms');

        if (Schema::hasColumn('system_settings', 'enable_sms_call_logging')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->dropColumn('enable_sms_call_logging');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('system_settings', 'enable_sms_call_logging')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->boolean('enable_sms_call_logging')->default(false);
            });
        }
    }
};
