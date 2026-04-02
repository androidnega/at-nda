<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UniversitySeeder extends Seeder
{
    /**
     * Faculties and departments per Takoradi Technical University official list (2026).
     */
    public function run(): void
    {
        DB::table('universities')->insert([
            'name' => 'Takoradi Technical University',
            'location' => 'Takoradi, Ghana',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $faculties = [
            ['name' => 'Faculty of Applied Arts and Technology', 'university_id' => 1],
            ['name' => 'Faculty of Applied Sciences', 'university_id' => 1],
            ['name' => 'Faculty of Business Studies', 'university_id' => 1],
            ['name' => 'Faculty of Built and Natural Environment', 'university_id' => 1],
            ['name' => 'Faculty of Engineering', 'university_id' => 1],
            ['name' => 'Faculty of Health and Allied Sciences', 'university_id' => 1],
            ['name' => 'Faculty of Maritime and Nautical Studies', 'university_id' => 1],
            ['name' => 'Faculty of Media Technology and Liberal Studies', 'university_id' => 1],
        ];

        foreach ($faculties as $f) {
            DB::table('faculties')->insert(array_merge($f, ['created_at' => now(), 'updated_at' => now()]));
        }

        $departments = [
            // Faculty of Applied Arts and Technology (1)
            ['Graphic Design Technology', 1],
            ['Ceramics Technology', 1],
            ['Sculpture and Industrial Crafts', 1],
            ['Industrial Painting and Design', 1],
            ['Textile Design and Technology', 1],
            ['Fashion Design and Technology', 1],
            // Faculty of Applied Sciences (2)
            ['Hospitality Management', 2],
            ['Tourism Management', 2],
            ['Mathematics, Statistics and Actuarial Science', 2],
            ['Computer Science', 2],
            // Faculty of Business Studies (3)
            ['Accounting and Finance', 3],
            ['Procurement and Supply', 3],
            ['Marketing and Strategy', 3],
            ['Secretaryship and Management Studies', 3],
            ['Professional Studies', 3],
            // Faculty of Built and Natural Environment (4)
            ['Building Technology', 4],
            ['Interior Design Technology', 4],
            ['Estate Management', 4],
            // Faculty of Engineering (5)
            ['Civil Engineering', 5],
            ['Electricals/Electronics Engineering', 5],
            ['Mechanical Engineering', 5],
            ['Oil & Natural Gas', 5],
            ['Renewable Energy Engineering', 5],
            // Faculty of Health and Allied Sciences (6)
            ['Medical Laboratory Sciences', 6],
            ['Industrial Laboratory Sciences', 6],
            ['Pharmaceutical Sciences', 6],
            // Faculty of Maritime and Nautical Studies (7)
            ['Marine Engineering', 7],
            ['Nautical Studies', 7],
            ['Maritime Transport', 7],
            // Faculty of Media Technology and Liberal Studies (8)
            ['Media and Digital Technology', 8],
            ['Communication Technology', 8],
        ];

        foreach ($departments as $d) {
            DB::table('departments')->insert([
                'name' => $d[0],
                'faculty_id' => $d[1],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
