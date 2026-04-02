<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'face_registered')) {
                $table->boolean('face_registered')->default(false)->after('profile_image');
            }
            if (! Schema::hasColumn('students', 'face_id')) {
                $table->string('face_id', 128)->nullable()->after('face_registered');
            }
        });

        Schema::create('face_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->boolean('success');
            $table->decimal('similarity', 8, 6)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('error_message', 512)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_verification_logs');

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'face_id')) {
                $table->dropColumn('face_id');
            }
            if (Schema::hasColumn('students', 'face_registered')) {
                $table->dropColumn('face_registered');
            }
        });
    }
};
