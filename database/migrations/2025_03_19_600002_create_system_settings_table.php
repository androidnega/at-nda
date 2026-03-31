<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enable_face_verification')->default(true);
            $table->boolean('enable_ip_binding')->default(true);
            $table->boolean('allow_multiple_index_on_device')->default(false);
            $table->decimal('face_match_threshold', 4, 2)->default(0.50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
