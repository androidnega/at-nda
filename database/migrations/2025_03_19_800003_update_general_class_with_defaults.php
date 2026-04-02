<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $general = DB::table('classes')->where('code', 'GEN')->first();
        if ($general && ($general->faculty_id === null || $general->level === null)) {
            $facultyId = DB::table('faculties')->value('id');
            if (! $facultyId) {
                $facultyId = DB::table('faculties')->insertGetId([
                    'name' => 'General Faculty',
                    'university_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $deptId = DB::table('departments')->where('faculty_id', $facultyId)->value('id');
            if (! $deptId) {
                $deptId = DB::table('departments')->insertGetId([
                    'name' => 'General Department',
                    'faculty_id' => $facultyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
