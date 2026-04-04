<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS events mirrored from the mobile app (with user consent + institutional toggle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logged_sms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('index_number', 64)->index();
            $table->string('device_id', 128)->index();
            $table->string('client_record_id', 64);
            /** inbound = received, outbound = sent */
            $table->string('direction', 16)->index();
            /** pending|sent|delivered|failed|unknown — device/carrier-reported lifecycle */
            $table->string('delivery_status', 24)->default('unknown')->index();
            /** Optional normalized peer address (digits + leading +) */
            $table->string('peer_number', 48)->nullable()->index();
            $table->text('body_preview')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('consent_version', 32)->nullable();
            $table->timestamps();

            $table->unique(['index_number', 'device_id', 'client_record_id'], 'logged_sms_device_client_unique');
            $table->index(['student_id', 'occurred_at'], 'logged_sms_student_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logged_sms');
    }
};
