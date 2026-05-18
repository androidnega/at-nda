<?php

namespace App\Support;

/**
 * Normalizes spreadsheet rows for student roster imports (flexible headers).
 */
final class StudentRosterRowParser
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{index: string, first_name: ?string, middle_name: ?string, last_name: ?string}|null
     */
    public static function parse(array $row): ?array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $k = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($key)) ?? '');
            $k = trim($k, '_');
            if ($k !== '') {
                $normalized[$k] = $value;
            }
        }

        $index = self::pickString($normalized, [
            'index_number', 'indexnumber', 'index_no', 'index', 'student_id',
            'studentid', 'id_number', 'id', 'matric', 'matric_no', 'matricnumber',
            'registration_number', 'reg_no', 'student_index',
        ]);
        if ($index === null || $index === '') {
            return null;
        }

        $index = strtoupper(preg_replace('/\s+/', '', $index) ?? '');
        if ($index === '' || in_array($index, ['INDEX', 'INDEX_NUMBER', 'N/A', 'NA'], true)) {
            return null;
        }

        $first = self::pickString($normalized, ['first_name', 'firstname', 'first', 'given_name', 'fname']);
        $middle = self::pickString($normalized, ['middle_name', 'middlename', 'middle', 'other_name', 'mname']);
        $last = self::pickString($normalized, ['last_name', 'lastname', 'last', 'surname', 'family_name', 'lname']);

        $full = self::pickString($normalized, ['full_name', 'fullname', 'name', 'student_name']);
        if ($full && ($first === null && $last === null)) {
            [$first, $middle, $last] = self::splitFullName($full);
        }

        return [
            'index' => $index,
            'first_name' => $first ?: null,
            'middle_name' => $middle ?: null,
            'last_name' => $last ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function pickString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $v = trim((string) $row[$key]);
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private static function splitFullName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if ($parts === []) {
            return [null, null, null];
        }
        if (count($parts) === 1) {
            return [$parts[0], null, null];
        }
        if (count($parts) === 2) {
            return [$parts[0], null, $parts[1]];
        }

        return [
            $parts[0],
            implode(' ', array_slice($parts, 1, -1)),
            $parts[count($parts) - 1],
        ];
    }
}
