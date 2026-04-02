<?php

namespace App\Imports;

use App\Models\SchoolClass;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClassStudentsImport implements ToModel, WithHeadingRow
{
    public function __construct(
        public SchoolClass $schoolClass
    ) {}

    public function model(array $row): ?Student
    {
        $index = strtoupper(trim((string) ($row['index_number'] ?? $row['indexnumber'] ?? $row['index'] ?? '')));
        if (empty($index)) {
            return null;
        }

        $firstName = trim((string) ($row['first_name'] ?? $row['firstname'] ?? $row['first'] ?? '')) ?: null;
        $middleName = trim((string) ($row['middle_name'] ?? $row['middlename'] ?? $row['middle'] ?? '')) ?: null;
        $lastName = trim((string) ($row['last_name'] ?? $row['lastname'] ?? $row['last'] ?? '')) ?: null;

        return Student::updateOrCreate(
            ['index_number' => $index],
            [
                'first_name' => $firstName,
                'middle_name' => $middleName ?: null,
                'last_name' => $lastName,
                'class_id' => $this->schoolClass->id,
            ]
        );
    }
}
