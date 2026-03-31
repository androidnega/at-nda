<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $general = DB::table('classes')->where('code', 'GEN')->first();
        if ($general && ($general->faculty_id === null || $general->level === null)) {
            $facultyId = DB::table('faculties')->value('id') ?? 1;
            $deptId = DB::table('departments')->where('faculty_id', $facultyId)->value('id') ?? 1;
            DB::table('classes')->where('id', $general->id)->update([
                'faculty_id' => $facultyId,
                'department_id' => $deptId,
                'level' => 100,
                'name' => 'General',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('classes')->where('code', 'GEN')->update([
            'faculty_id' => null,
            'department_id' => null,
            'level' => null,
            'updated_at' => now(),
        ]);
    }
};
