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
            // Lowercase first, THEN strip non-alphanumerics, otherwise headers
            // like "Index Number" lose their uppercase letters to the regex
            // (e.g. became `_ndex_umber`) and never matched any alias.
            $k = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($key))) ?? '';
            $k = trim($k, '_');
            if ($k !== '') {
                $normalized[$k] = $value;
            }
        }

        $index = self::pickString($normalized, [
            'index_number', 'index_numbers', 'indexnumber', 'indexnumbers',
            'index_no', 'index_nos', 'indexno', 'indexnos', 'index', 'indexes', 'indices',
            'student_index', 'student_indexes', 'student_index_number', 'student_index_numbers',
            'studentindex', 'studentindexnumber',
            'student_id', 'studentid', 'student_ids',
            'id_number', 'id_numbers', 'idnumber', 'id', 'ids',
            'matric', 'matric_no', 'matricnumber', 'matric_number', 'matric_numbers',
            'registration_number', 'registration_no', 'reg_no', 'reg_number', 'regnumber',
        ]);
        if ($index === null || $index === '') {
            // Last-resort heuristic: if none of the known header aliases hit,
            // scan the row for the first value that looks like a student
            // index (alphanumeric, ≥4 chars, contains a digit). Helps with
            // bizarre headers like "Index #" or unnamed columns.
            $index = self::guessIndexValue($normalized);
            if ($index === null) {
                return null;
            }
        }

        $index = strtoupper(preg_replace('/\s+/', '', $index) ?? '');
        if ($index === '' || in_array($index, ['INDEX', 'INDEX_NUMBER', 'INDEX_NUMBERS', 'N/A', 'NA', 'NAN', 'NULL'], true)) {
            return null;
        }

        $first = self::pickString($normalized, [
            'first_name', 'first_names', 'firstname', 'firstnames', 'first', 'given_name', 'given_names', 'fname',
        ]);
        $middle = self::pickString($normalized, [
            'middle_name', 'middle_names', 'middlename', 'middlenames', 'middle', 'other_name', 'other_names', 'othername', 'mname',
        ]);
        $last = self::pickString($normalized, [
            'last_name', 'last_names', 'lastname', 'lastnames', 'last', 'surname', 'surnames', 'family_name', 'family_names', 'lname',
        ]);

        $full = self::pickString($normalized, [
            'full_name', 'full_names', 'fullname', 'fullnames',
            'name', 'names', 'student_name', 'student_names', 'student_full_name',
        ]);
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
     * Pick the first value that looks like a roster index number, ignoring
     * known name / class columns.
     *
     * @param  array<string, mixed>  $row
     */
    private static function guessIndexValue(array $row): ?string
    {
        $skipKeys = [
            'first_name', 'first_names', 'firstname', 'firstnames', 'first', 'given_name', 'given_names', 'fname',
            'middle_name', 'middle_names', 'middlename', 'middlenames', 'middle', 'other_name', 'other_names', 'othername', 'mname',
            'last_name', 'last_names', 'lastname', 'lastnames', 'last', 'surname', 'surnames', 'family_name', 'family_names', 'lname',
            'full_name', 'full_names', 'fullname', 'fullnames', 'name', 'names', 'student_name', 'student_names', 'student_full_name',
            'class', 'class_id', 'class_name', 'classname', 'cohort', 'group',
            'email', 'phone', 'phone_number', 'phonenumber', 'mobile', 'tel',
            'program', 'department', 'faculty', 'level', 'year', 'semester', 'gender', 'date_of_birth', 'dob',
        ];
        foreach ($row as $key => $value) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }
            $v = trim((string) $value);
            if ($v === '') {
                continue;
            }
            $compact = preg_replace('/\s+/', '', $v) ?? '';
            if (strlen($compact) < 4) {
                continue;
            }
            // Indices are typically alphanumeric and contain at least one digit.
            if (preg_match('/^[A-Za-z0-9\-\/]+$/', $compact) && preg_match('/\d/', $compact)) {
                return $compact;
            }
        }

        return null;
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
