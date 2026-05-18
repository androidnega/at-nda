<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('universities') && ! Schema::hasColumn('universities', 'logo_path')) {
            Schema::table('universities', function (Blueprint $table): void {
                $table->string('logo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('universities', 'logo_path')) {
            Schema::table('universities', function (Blueprint $table): void {
                $table->dropColumn('logo_path');
            });
        }
    }
};
