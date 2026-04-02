<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'face_descriptor')) {
                $table->longText('face_descriptor')->nullable()->after('profile_image');
            }
            if (!Schema::hasColumn('students', 'bound_ip')) {
                $table->string('bound_ip', 45)->nullable()->after('face_descriptor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['face_descriptor', 'bound_ip']);
        });
    }
};
