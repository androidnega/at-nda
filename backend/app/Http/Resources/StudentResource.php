<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Student $student */
        $student = $this->resource;
        $name = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));
        if ($name === '') {
            $name = $student->getDisplayNameOrIndex();
        }

        return [
            'id' => $student->id,
            'name' => $name,
            'index_number' => $student->index_number,
            'course' => $student->course ?? null,
        ];
    }
}
