<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync faculties and departments with Takoradi Technical University official list (2026).
     */
    public function up(): void
    {
        $universityId = DB::table('universities')->where('name', 'Takoradi Technical University')->value('id') ?? 1;

        // 1. Add new faculties (Health, Maritime, Media)
        $existingFaculties = DB::table('faculties')->pluck('id', 'name')->toArray();

        $facultiesToAdd = [
            'Faculty of Health and Allied Sciences',
            'Faculty of Maritime and Nautical Studies',
            'Faculty of Media Technology and Liberal Studies',
        ];

        foreach ($facultiesToAdd as $name) {
            if (!in_array($name, array_keys($existingFaculties))) {
                DB::table('faculties')->insert([
                    'name' => $name,
                    'university_id' => $universityId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Refresh faculty IDs
        $faculties = DB::table('faculties')->pluck('id', 'name')->toArray();

        $engId = $faculties['Faculty of Engineering'] ?? 1;
        $appSciId = $faculties['Faculty of Applied Sciences'] ?? 2;
        $busId = $faculties['Faculty of Business Studies'] ?? 3;
        $builtId = $faculties['Faculty of Built and Natural Environment'] ?? 4;
        $artsId = $faculties['Faculty of Applied Arts and Technology'] ?? 5;
        $healthId = $faculties['Faculty of Health and Allied Sciences'] ?? null;
        $maritimeId = $faculties['Faculty of Maritime and Nautical Studies'] ?? null;
        $mediaId = $faculties['Faculty of Media Technology and Liberal Studies'] ?? null;

        // 2. Simple renames (update in place)
        $renames = [
            'Electrical and Electronic Engineering' => 'Electricals/Electronics Engineering',
            'Oil and Natural Gas Engineering' => 'Oil & Natural Gas',
            'Painting and Decorating Technology' => 'Industrial Painting and Design',
            'Textile Design Technology' => 'Textile Design and Technology',
            'Fashion Design Technology' => 'Fashion Design and Technology',
            'Marketing' => 'Marketing and Strategy',
            'Procurement and Supply Chain Management' => 'Procurement and Supply',
        ];

        foreach ($renames as $old => $new) {
            DB::table('departments')->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
        }

        // 3. Merge: Accounting + Banking and Finance -> Accounting and Finance
        $acctId = DB::table('departments')->where('name', 'Accounting')->where('faculty_id', $busId)->value('id');
        $bankId = DB::table('departments')->where('name', 'Banking and Finance')->where('faculty_id', $busId)->value('id');
        $acctFinanceId = $acctId ?: $bankId;
        if ($acctId && $bankId && $acctId !== $bankId) {
            DB::table('students')->where('department_id', $bankId)->update(['department_id' => $acctId]);
            DB::table('classes')->where('department_id', $bankId)->update(['department_id' => $acctId]);
            DB::table('departments')->where('id', $bankId)->delete();
        }
        if ($acctFinanceId) {
            DB::table('departments')->where('id', $acctFinanceId)->update(['name' => 'Accounting and Finance', 'updated_at' => now()]);
        }

        // 4. Merge: Mathematics + Statistics -> Mathematics, Statistics and Actuarial Science
        $mathId = DB::table('departments')->where('name', 'Mathematics')->where('faculty_id', $appSciId)->value('id');
        $statId = DB::table('departments')->where('name', 'Statistics')->where('faculty_id', $appSciId)->value('id');
        $targetId = $mathId ?: $statId;
        if ($targetId) {
            if ($mathId && $statId && $mathId !== $statId) {
                DB::table('students')->where('department_id', $statId)->update(['department_id' => $mathId]);
                DB::table('classes')->where('department_id', $statId)->update(['department_id' => $mathId]);
                DB::table('departments')->where('id', $statId)->delete();
            }
            DB::table('departments')->where('id', $targetId)->update(['name' => 'Mathematics, Statistics and Actuarial Science', 'updated_at' => now()]);
        }

        // 5. Split: Hospitality and Tourism Management -> add Hospitality Management + Tourism Management, null refs, delete old
        $htId = DB::table('departments')->where('name', 'Hospitality and Tourism Management')->where('faculty_id', $appSciId)->value('id');
        if ($htId) {
            DB::table('students')->where('department_id', $htId)->update(['department_id' => null]);
            DB::table('classes')->where('department_id', $htId)->update(['department_id' => null]);
            DB::table('departments')->where('id', $htId)->delete();
        }

        // 6. Add new departments
        $newDepts = [
            [$artsId, 'Sculpture and Industrial Crafts'],
            [$appSciId, 'Hospitality Management'],
            [$appSciId, 'Tourism Management'],
            [$appSciId, 'Mathematics, Statistics and Actuarial Science'], // in case math/stat were missing
            [$busId, 'Professional Studies'],
            [$engId, 'Renewable Energy Engineering'],
        ];

        foreach ($newDepts as [$fid, $name]) {
            if ($fid && !DB::table('departments')->where('faculty_id', $fid)->where('name', $name)->exists()) {
                DB::table('departments')->insert([
                    'name' => $name,
                    'faculty_id' => $fid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // New faculties' departments
        if ($healthId) {
            foreach (['Medical Laboratory Sciences', 'Industrial Laboratory Sciences', 'Pharmaceutical Sciences'] as $name) {
                if (!DB::table('departments')->where('faculty_id', $healthId)->where('name', $name)->exists()) {
                    DB::table('departments')->insert([
                        'name' => $name,
                        'faculty_id' => $healthId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        if ($maritimeId) {
            foreach (['Marine Engineering', 'Nautical Studies', 'Maritime Transport'] as $name) {
                if (!DB::table('departments')->where('faculty_id', $maritimeId)->where('name', $name)->exists()) {
                    DB::table('departments')->insert([
                        'name' => $name,
                        'faculty_id' => $maritimeId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        if ($mediaId) {
            foreach (['Media and Digital Technology', 'Communication Technology'] as $name) {
                if (!DB::table('departments')->where('faculty_id', $mediaId)->where('name', $name)->exists()) {
                    DB::table('departments')->insert([
                        'name' => $name,
                        'faculty_id' => $mediaId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 7. Delete departments not in official list (FKs will null via constraint)
        $officialDepts = [
            'Graphic Design Technology', 'Ceramics Technology', 'Sculpture and Industrial Crafts',
            'Industrial Painting and Design', 'Textile Design and Technology', 'Fashion Design and Technology',
            'Hospitality Management', 'Tourism Management', 'Mathematics, Statistics and Actuarial Science', 'Computer Science',
            'Accounting and Finance', 'Procurement and Supply', 'Marketing and Strategy',
            'Secretaryship and Management Studies', 'Professional Studies',
            'Building Technology', 'Interior Design Technology', 'Estate Management',
            'Civil Engineering', 'Electricals/Electronics Engineering', 'Mechanical Engineering', 'Oil & Natural Gas', 'Renewable Energy Engineering',
            'Medical Laboratory Sciences', 'Industrial Laboratory Sciences', 'Pharmaceutical Sciences',
            'Marine Engineering', 'Nautical Studies', 'Maritime Transport',
            'Media and Digital Technology', 'Communication Technology',
        ];

        $toDelete = DB::table('departments')
            ->whereNotIn('name', $officialDepts)
            ->pluck('id');

        foreach ($toDelete as $id) {
            DB::table('students')->where('department_id', $id)->update(['department_id' => null]);
            DB::table('classes')->where('department_id', $id)->update(['department_id' => null]);
            DB::table('departments')->where('id', $id)->delete();
        }
    }

    public function down(): void
    {
        // Cannot safely reverse - original data is lost
    }
};
