<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances') || Schema::hasColumn('attendances', 'user_agent')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('user_agent', 512)->nullable()->after('device_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances') || ! Schema::hasColumn('attendances', 'user_agent')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
