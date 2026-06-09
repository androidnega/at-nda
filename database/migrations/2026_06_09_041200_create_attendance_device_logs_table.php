<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-submission device telemetry, captured at the moment a student
 * marks attendance online.
 *
 * Purpose: post-hoc fraud review. Rows are NEVER consulted to allow or
 * block an attendance — they're only summarised by AttendanceRiskService
 * to produce a non-blocking risk_score on the attendances row.
 *
 * fingerprint_hash comes from the FingerprintJS visitor id (32-char hex);
 * the rest is browser/platform metadata pulled from window.navigator on
 * the client side and a few HTTP headers on the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_device_logs')) {
            return;
        }

        Schema::create('attendance_device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('session_id')
                ->constrained('attendance_sessions')
                ->cascadeOnDelete();
            $table->string('fingerprint_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 480)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('operating_system', 80)->nullable();
            $table->string('screen_resolution', 32)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('language', 16)->nullable();
            $table->unsignedSmallInteger('device_memory')->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->boolean('touch_support')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Drives Rule 1 (same fingerprint, same session) and Rule 3
            // (same fingerprint across many sessions).
            $table->index(['fingerprint_hash', 'session_id']);
            $table->index('fingerprint_hash');
            // Drives Rule 2 (same IP, same session).
            $table->index(['ip_address', 'session_id']);
            // Drives Rule 4 (per-student device-switching history).
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_logs');
    }
};
