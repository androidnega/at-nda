<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('faculty_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('level')->nullable()->after('name')->comment('100, 200, etc.');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['faculty_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['faculty_id', 'department_id', 'level']);
        });
    }
};
