<?php

namespace App\Imports;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\StudentRosterRowParser;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClassStudentsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function __construct(
        public SchoolClass $schoolClass
    ) {}

    public function collection(Collection $rows): void
    {
        $this->schoolClass->loadMissing(['department', 'faculty']);
        $departmentId = $this->schoolClass->department_id;

        foreach ($rows as $row) {
            if (! $row instanceof Collection) {
                continue;
            }
            $parsed = StudentRosterRowParser::parse($row->toArray());
            if ($parsed === null) {
                $this->skipped++;

                continue;
            }

            $existing = Student::query()->where('index_number', $parsed['index'])->first();
            $payload = [
                'first_name' => $parsed['first_name'],
                'middle_name' => $parsed['middle_name'],
                'last_name' => $parsed['last_name'],
                'class_id' => $this->schoolClass->id,
            ];
            if ($departmentId) {
                $payload['department_id'] = $departmentId;
            }

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                Student::create(array_merge($payload, [
                    'index_number' => $parsed['index'],
                    'password' => null,
                ]));
                $this->created++;
            }
        }
    }
}
