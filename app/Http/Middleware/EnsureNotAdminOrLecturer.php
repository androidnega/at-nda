<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotAdminOrLecturer
{
    /**
     * Block admin/lecturer from attendance marking routes (students only).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('admin_id') || $request->session()->has('lecturer_id')) {
            return redirect()->route('dashboard.dashboard')->with('error', 'Admins and lecturers cannot mark attendance. Use the homepage as a student.');
        }
        return $next($request);
    }
}
