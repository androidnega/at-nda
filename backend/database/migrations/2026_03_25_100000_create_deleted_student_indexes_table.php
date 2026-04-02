<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_student_indexes', function (Blueprint $table) {
            $table->id();
            $table->string('index_number', 64)->index();
            $table->timestamp('deleted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_student_indexes');
    }
};
