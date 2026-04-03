<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'dynamic_ui')) {
                // JSON list controlled by the backend; optional for the app (Flutter renders only when present).
                $table->json('dynamic_ui')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('system_settings', 'dynamic_ui')) {
                $table->dropColumn('dynamic_ui');
            }
        });
    }
};

