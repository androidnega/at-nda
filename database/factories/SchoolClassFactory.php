<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 *
 * Minimal factory aligned with the base `2025_03_19_700001_create_classes_table`
 * migration (id, name, nullable code). Later migrations add university_id,
 * faculty_id, department_id, level, qualification, semester_id, logo_path —
 * all nullable, so callers can layer them in via ->state([...]) when a test
 * needs them. Built to support Phase 1 P1.T20 (StudentsAuthTest) which only
 * needs an id to wire Student::class_id; do not over-fit on richer fields.
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Class ?? ##'),
            'code' => fake()->unique()->bothify('CLS-###'),
        ];
    }
}
