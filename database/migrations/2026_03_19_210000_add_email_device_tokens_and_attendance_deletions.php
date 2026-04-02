<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('last_name');
        });

        Schema::create('student_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('firebase_token');
            $table->timestamps();
            $table->unique('student_id');
        });

        Schema::create('attendance_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_id');
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('deleted_at')->useCurrent();
            $table->index('deleted_at');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_deletions');
        Schema::dropIfExists('student_device_tokens');

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
