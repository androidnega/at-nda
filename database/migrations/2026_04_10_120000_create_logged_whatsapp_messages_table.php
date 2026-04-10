<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logged_whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('index_number', 64)->index();
            $table->string('device_id', 128);
            $table->string('client_record_id', 64);
            $table->string('source_app', 32)->default('whatsapp');
            $table->string('sender_hint', 120)->nullable();
            $table->text('body_preview');
            $table->timestamp('occurred_at')->index();
            $table->string('consent_version', 32)->nullable();
            $table->timestamps();

            $table->unique(['index_number', 'device_id', 'client_record_id'], 'uq_whatsapp_client_record');
            $table->index(['index_number', 'occurred_at'], 'idx_whatsapp_index_occurred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logged_whatsapp_messages');
    }
};
