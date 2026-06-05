<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_active_sessions')) {
            return;
        }

        Schema::create('student_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            // Laravel session id from session()->getId(); rotated on login.
            $table->string('session_id', 100);
            // Long-lived signed cookie value that survives session clear.
            $table->string('device_fingerprint', 64)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id'], 'student_active_sessions_sid_unique');
            $table->index(['student_id', 'is_active'], 'student_active_sessions_active');
            $table->index(['device_fingerprint', 'student_id'], 'student_active_sessions_fp_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_active_sessions');
    }
};
