<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Lecturer;
use App\Support\LecturerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ResolvesLecturerScope
{
    /**
     * @return Collection<int, int>|null null = admin (no class filter)
     */
    protected function lecturerClassIdsFromSession(Request $request): ?Collection
    {
        $lecturer = LecturerAccess::lecturerFromSession($request);
        if (! $lecturer) {
            return null;
        }

        return $lecturer->assignedClassIds();
    }

    protected function requireLecturer(Request $request): ?Lecturer
    {
        return LecturerAccess::lecturerFromSession($request);
    }
}
