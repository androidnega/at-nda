<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });

        DB::table('students')->whereIn('first_name', ['Unknown', ''])->update(['first_name' => null]);
        DB::table('students')->whereIn('last_name', ['', 'Unknown'])->update(['last_name' => null]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });
    }
};
