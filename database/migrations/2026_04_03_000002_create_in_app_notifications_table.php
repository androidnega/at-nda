<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 64)->index();
            $table->string('title', 255);
            $table->text('body');
            $table->timestamp('starts_at')->nullable();

            // Uniqueness prevents the cron job from sending duplicates every minute.
            $table->string('delivery_key', 255)->unique();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
    }
};

