<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call events mirrored from the mobile app (with user consent + institutional toggle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('index_number', 64)->index();
            $table->string('device_id', 128)->index();
            $table->string('client_record_id', 64);
            /** inbound | outbound */
            $table->string('direction', 16)->index();
            /** missed|answered|rejected|failed|unknown|in_progress */
            $table->string('call_outcome', 24)->default('unknown')->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('peer_number', 48)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->string('consent_version', 32)->nullable();
            $table->timestamps();

            $table->unique(['index_number', 'device_id', 'client_record_id'], 'call_logs_device_client_unique');
            $table->index(['student_id', 'occurred_at'], 'call_logs_student_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
