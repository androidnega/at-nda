<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultClassId = DB::table('classes')->insertGetId([
            'name' => 'General',
            'code' => 'GEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('courses')->whereNull('class_id')->update(['class_id' => $defaultClassId]);
    }

    public function down(): void
    {
        DB::table('courses')->update(['class_id' => null]);
        DB::table('classes')->where('code', 'GEN')->delete();
    }
};
