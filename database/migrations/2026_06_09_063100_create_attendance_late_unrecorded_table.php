<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holds attendance submissions that arrived AFTER the session ended or
 * past the attendance window. The classic flow would 422-reject them
 * and the row would churn forever in the mobile outbox. With this table
 * we persist the intent — the class rep / lecturer reviews and
 * approves or denies from the web dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_late_unrecorded')) {
            return;
        }

        Schema::create('attendance_late_unrecorded', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('attendance_uuid', 64)->nullable();
            $t->unsignedBigInteger('student_id');
            $t->unsignedBigInteger('attendance_session_id')->nullable();
            $t->unsignedBigInteger('course_id')->nullable();
            $t->unsignedBigInteger('attendance_week_id')->nullable();

            // Reason the API rejected the mark when the offline sync arrived
            // ("session_expired", "outside_window", etc). Filled by the API.
            $t->string('reason', 64)->nullable();

            // Full client payload — gives the lecturer everything needed
            // to verify (lat, lng, accuracy, qr_code, device_ip, etc).
            $t->json('payload');

            // When the student tapped Mark on the device.
            $t->timestamp('captured_at')->nullable();
            // When the server first saw the request.
            $t->timestamp('sync_attempted_at')->nullable();

            // pending → approved | denied. Approval inserts a real
            // Attendance row referencing this id.
            $t->enum('decision', ['pending', 'approved', 'denied'])->default('pending');
            $t->timestamp('decided_at')->nullable();
            $t->unsignedBigInteger('decided_by_user_id')->nullable();
            $t->string('decision_notes', 512)->nullable();

            // Once approved, this is the id of the resulting attendances row.
            $t->unsignedBigInteger('resulting_attendance_id')->nullable();

            $t->timestamps();

            $t->unique('attendance_uuid', 'late_attendance_uuid_unique');
            $t->index(['student_id', 'decision'], 'late_attendance_student_decision_idx');
            $t->index('attendance_session_id', 'late_attendance_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_late_unrecorded');
    }
};
