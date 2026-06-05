<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Logical actor: student / lecturer / admin id (depends on role).
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->string('actor_name', 191)->nullable();
            // Optional scope: class & course context for filtering by reps.
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('attendance_session_id')->nullable();
            // What happened: snake_case verb (session_opened, mark_created, ...).
            $table->string('action', 64);
            // What it acted on: e.g. "attendance:123", "session:456".
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            // Free-form structured details (reason, before / after values, etc).
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'created_at'], 'audit_logs_class_time');
            $table->index(['course_id', 'created_at'], 'audit_logs_course_time');
            $table->index(['action', 'created_at'], 'audit_logs_action_time');
            $table->index('attendance_session_id', 'audit_logs_session');
            $table->index('actor_id', 'audit_logs_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
