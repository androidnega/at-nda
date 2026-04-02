<?php

namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToModel, WithHeadingRow
{
    public function model(array $row): ?Student
    {
        $index = strtoupper(trim((string) ($row['index_number'] ?? $row['indexnumber'] ?? $row['index'] ?? '')));
        if (empty($index)) {
            return null;
        }

        $firstName = trim((string) ($row['first_name'] ?? $row['firstname'] ?? $row['first'] ?? '')) ?: null;
        $middleName = trim((string) ($row['middle_name'] ?? $row['middlename'] ?? $row['middle'] ?? '')) ?: null;
        $lastName = trim((string) ($row['last_name'] ?? $row['lastname'] ?? $row['last'] ?? '')) ?: null;

        $classId = null;
        if (isset($row['class_id']) && is_numeric($row['class_id']) && (int) $row['class_id'] > 0) {
            $classId = (int) $row['class_id'];
        } elseif (!empty($row['class'] ?? $row['class_name'] ?? null)) {
            $classMatch = \App\Models\SchoolClass::where('name', 'like', '%' . trim($row['class'] ?? $row['class_name']) . '%')->first();
            $classId = $classMatch?->id;
        }

        $data = [
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
        ];
        if ($classId) {
            $data['class_id'] = $classId;
        }

        return Student::updateOrCreate(['index_number' => $index], $data);
    }
}
