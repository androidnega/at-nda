<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff dashboard routes that only real administrators (session admin_id) may use.
 */
class EnsureAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('admin_id')) {
            return redirect()->route('dashboard.dashboard')
                ->with('error', 'Only administrators can manage staff accounts.');
        }

        return $next($request);
    }
}
