<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove duplicate faculties and departments (keep one per unique name).
     */
    public function up(): void
    {
        $this->deduplicateFaculties();
        $this->deduplicateDepartments();
    }

    private function deduplicateFaculties(): void
    {
        $faculties = DB::table('faculties')->orderBy('name')->orderBy('id')->get();
        $byName = $faculties->groupBy('name');

        foreach ($byName as $name => $group) {
            $keepId = $group->min('id');
            $duplicateIds = $group->pluck('id')->filter(fn ($id) => $id !== $keepId)->values()->all();

            if (empty($duplicateIds)) {
                continue;
            }

            DB::table('departments')->whereIn('faculty_id', $duplicateIds)->update(['faculty_id' => $keepId]);
            DB::table('classes')->whereIn('faculty_id', $duplicateIds)->update(['faculty_id' => $keepId]);

            DB::table('faculties')->whereIn('id', $duplicateIds)->delete();
        }
    }

    private function deduplicateDepartments(): void
    {
        $departments = DB::table('departments')->orderBy('name')->orderBy('id')->get();
        $byNameAndFaculty = $departments->groupBy(fn ($d) => $d->name . '|' . $d->faculty_id);

        foreach ($byNameAndFaculty as $key => $group) {
            $keepId = $group->min('id');
            $duplicateIds = $group->pluck('id')->filter(fn ($id) => $id !== $keepId)->values()->all();

            if (empty($duplicateIds)) {
                continue;
            }

            DB::table('students')->whereIn('department_id', $duplicateIds)->update(['department_id' => $keepId]);
            DB::table('classes')->whereIn('department_id', $duplicateIds)->update(['department_id' => $keepId]);

            DB::table('departments')->whereIn('id', $duplicateIds)->delete();
        }
    }

    public function down(): void
    {
        // Cannot reverse deduplication
    }
};
