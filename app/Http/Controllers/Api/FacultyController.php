<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    /**
     * List faculties (unique by name).
     */
    public function index(): JsonResponse
    {
        $faculties = Faculty::orderBy('name')->get(['id', 'name']);
        return response()->json($faculties);
    }

    /**
     * List departments for a faculty.
     */
    public function departments(Request $request): JsonResponse
    {
        $facultyId = $request->query('faculty_id');
        if (!$facultyId) {
            return response()->json(['message' => 'faculty_id required'], 400);
        }

        $departments = Department::where('faculty_id', $facultyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($departments);
    }
}
