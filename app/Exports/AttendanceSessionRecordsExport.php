<?php

namespace App\Exports;

use App\Models\Attendance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceSessionRecordsExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly int $sessionId,
    ) {}

    public function headings(): array
    {
        return ['Name', 'Index number', 'Time marked'];
    }

    public function collection(): Collection
    {
        return Attendance::query()
            ->with('student')
            ->where('attendance_session_id', $this->sessionId)
            ->orderBy('attendance_time')
            ->get()
            ->map(function (Attendance $a) {
                $s = $a->student;
                $name = $s?->getDisplayNameOrIndex() ?? '—';
                $idx = $s?->index_number ?? '';
                $markedAt = $a->attendance_time?->toIso8601String() ?? $a->created_at?->toIso8601String();

                return [
                    (string) $name,
                    (string) $idx,
                    (string) $markedAt,
                ];
            });
    }
}

