<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesLecturerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturerClassController extends Controller
{
    use ResolvesLecturerScope;

    public function index(Request $request): View|RedirectResponse
    {
        $lecturer = $this->requireLecturer($request);
        if (! $lecturer) {
            return redirect()->route('lecturer.login');
        }

        $classes = $lecturer->assignedSchoolClasses();

        return view('lecturer.my-classes', [
            'classes' => $classes,
            'dashboardRole' => 'lecturer',
        ]);
    }
}
