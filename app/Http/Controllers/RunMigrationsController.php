<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class RunMigrationsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $providedKey = (string) $request->query('key', '');
        $expectedKey = (string) config('app.run_migrations_key', '');

        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response('Unauthorized', 403);
        }

        Artisan::call('migrate', ['--force' => true]);

        $output = trim((string) Artisan::output());
        if ($output === '') {
            $output = 'Migrations command executed.';
        }

        return response("Migration run completed.\n\n" . $output, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
