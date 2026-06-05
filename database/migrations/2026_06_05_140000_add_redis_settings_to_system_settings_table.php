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
            if (! Schema::hasColumn('system_settings', 'cache_driver')) {
                $table->string('cache_driver', 16)->nullable()->after('allow_rep_attendance_deletion');
            }
            if (! Schema::hasColumn('system_settings', 'redis_host')) {
                $table->string('redis_host')->nullable()->after('cache_driver');
            }
            if (! Schema::hasColumn('system_settings', 'redis_port')) {
                $table->unsignedSmallInteger('redis_port')->nullable()->after('redis_host');
            }
            if (! Schema::hasColumn('system_settings', 'redis_database')) {
                $table->unsignedTinyInteger('redis_database')->nullable()->after('redis_port');
            }
            if (! Schema::hasColumn('system_settings', 'redis_password_encrypted')) {
                $table->text('redis_password_encrypted')->nullable()->after('redis_database');
            }
            if (! Schema::hasColumn('system_settings', 'redis_prefix')) {
                $table->string('redis_prefix', 64)->nullable()->after('redis_password_encrypted');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            foreach ([
                'redis_prefix',
                'redis_password_encrypted',
                'redis_database',
                'redis_port',
                'redis_host',
                'cache_driver',
            ] as $col) {
                if (Schema::hasColumn('system_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
