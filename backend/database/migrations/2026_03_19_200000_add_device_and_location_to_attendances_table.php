<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('status');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->text('qr_code')->nullable()->after('lng');
            $table->json('face_descriptor')->nullable()->after('qr_code');
            $table->string('device_ip', 45)->nullable()->after('face_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'qr_code', 'face_descriptor', 'device_ip']);
        });
    }
};
