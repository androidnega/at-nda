<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('password_reset_codes')) {
            return;
        }

        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('index_number');
            $table->string('email');
            $table->string('code_hash');
            $table->string('request_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['index_number', 'expires_at'], 'pwd_reset_codes_lookup');
            $table->index('email', 'pwd_reset_codes_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
