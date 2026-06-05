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
            if (! Schema::hasColumn('system_settings', 'mail_enabled')) {
                $table->boolean('mail_enabled')->default(false)->after('auth_hero_image_path');
            }
            if (! Schema::hasColumn('system_settings', 'mail_host')) {
                $table->string('mail_host')->nullable()->after('mail_enabled');
            }
            if (! Schema::hasColumn('system_settings', 'mail_port')) {
                $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            }
            if (! Schema::hasColumn('system_settings', 'mail_encryption')) {
                $table->string('mail_encryption', 16)->nullable()->after('mail_port');
            }
            if (! Schema::hasColumn('system_settings', 'mail_username')) {
                $table->string('mail_username')->nullable()->after('mail_encryption');
            }
            if (! Schema::hasColumn('system_settings', 'mail_password_encrypted')) {
                $table->text('mail_password_encrypted')->nullable()->after('mail_username');
            }
            if (! Schema::hasColumn('system_settings', 'mail_from_address')) {
                $table->string('mail_from_address')->nullable()->after('mail_password_encrypted');
            }
            if (! Schema::hasColumn('system_settings', 'mail_from_name')) {
                $table->string('mail_from_name')->nullable()->after('mail_from_address');
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
                'mail_from_name',
                'mail_from_address',
                'mail_password_encrypted',
                'mail_username',
                'mail_encryption',
                'mail_port',
                'mail_host',
                'mail_enabled',
            ] as $col) {
                if (Schema::hasColumn('system_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
