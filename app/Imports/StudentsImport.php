<?php

namespace App\Imports;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\StudentRosterRowParser;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            if (! $row instanceof Collection) {
                continue;
            }
            $array = $row->toArray();
            $parsed = StudentRosterRowParser::parse($array);
            if ($parsed === null) {
                $this->skipped++;

                continue;
            }

            $classId = $this->resolveClassId($array);
            $departmentId = null;
            if ($classId) {
                $class = SchoolClass::query()->find($classId);
                $departmentId = $class?->department_id;
            }

            $payload = [
                'first_name' => $parsed['first_name'],
                'middle_name' => $parsed['middle_name'],
                'last_name' => $parsed['last_name'],
            ];
            if ($classId) {
                $payload['class_id'] = $classId;
            }
            if ($departmentId) {
                $payload['department_id'] = $departmentId;
            }

            if (! $classId) {
                $existing = Student::query()->where('index_number', $parsed['index'])->first();
                if (! $existing) {
                    $this->skipped++;

                    continue;
                }
            }

            $student = Student::upsertFromRoster($parsed['index'], $payload);
            if ($student->wasRecentlyCreated) {
                $this->created++;
            } else {
                $this->updated++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveClassId(array $row): ?int
    {
        if (isset($row['class_id']) && is_numeric($row['class_id']) && (int) $row['class_id'] > 0) {
            return (int) $row['class_id'];
        }

        $label = trim((string) ($row['class'] ?? $row['class_name'] ?? $row['cohort'] ?? ''));
        if ($label === '') {
            return null;
        }

        $class = SchoolClass::query()
            ->where('name', $label)
            ->orWhere('name', 'like', $label)
            ->orWhere('code', $label)
            ->first();

        return $class?->id;
    }
}
