<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_materials')) {
            return;
        }

        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            // class_id keeps a material scoped to a single class even when the
            // course is shared between cohorts (via course_class).
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by_student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['class_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
