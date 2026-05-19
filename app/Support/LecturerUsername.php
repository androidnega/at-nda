<?php

namespace App\Support;

use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class LecturerUsername
{
    public static function assignIfMissing(Lecturer $lecturer): void
    {
        if (! Schema::hasColumn('lecturers', 'username')) {
            return;
        }

        $current = trim((string) ($lecturer->username ?? ''));
        if ($current !== '') {
            return;
        }

        $lecturer->username = self::generateUnique($lecturer->name, $lecturer->id);
        $lecturer->saveQuietly();
    }

    public static function generateUnique(string $name, int $lecturerId): string
    {
        $words = preg_split('/\s+/', strtolower(trim($name))) ?: [];
        $first = $words[0] ?? 'lecturer';
        $last = $words[count($words) - 1] ?? 'staff';
        $baseA = preg_replace('/[^a-z0-9]/', '', $first.'.'.$last);
        $baseB = preg_replace('/[^a-z0-9]/', '', substr($first, 0, 1).$last);
        $suffixes = ['byte', 'core', 'logic', 'stack', 'matrix', 'vector', 'quant', 'kernel'];
        $baseC = preg_replace('/[^a-z0-9]/', '', $first.$suffixes[$lecturerId % count($suffixes)]);
        $candidates = array_values(array_unique(array_filter([$baseA, $baseB, $baseC, 'lecturer'.$lecturerId])));

        foreach ($candidates as $candidate) {
            if (! Lecturer::where('id', '!=', $lecturerId)->where('username', $candidate)->exists()
                && ! User::where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'lecturer'.$lecturerId;
    }
}
