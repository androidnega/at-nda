<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // When APP_URL is https, generated route() URLs stay https (avoids http links on mobile / cleartext blocks).
        $appUrl = (string) config('app.url', '');
        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        // Production safety guards (Phase 1 / remediation §C-01, C-04,
        // C-05, M-03). Each guard is a no-op outside APP_ENV=production.
        // The artisan console is also exempt so emergency commands
        // (queue worker, migrate, qr:rotate-secret, tinker) can still
        // run even when an env var is briefly missing or wrong.
        if (app()->environment('production') && ! app()->runningInConsole()) {
            $this->assertProductionEnvironment();
        }

        // Override config('mail') with the admin-configured SMTP credentials
        // stored in `system_settings`, so super-admins can manage email
        // delivery from the dashboard without redeploying .env.
        \App\Support\MailRuntimeConfig::applyOnce();

        // Allow super-admins to switch the cache driver between database
        // (default, safe on every shared host) and Redis (drops "resource
        // exhausted" errors when many students mark simultaneously) from
        // the same settings page.
        \App\Support\RedisRuntimeConfig::applyOnce();

        RateLimiter::for('api-v1', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('api-v1-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        View::composer(['layouts.classrep', 'layouts.student'], function ($view) {
            // Every rep / student page render passes through here. A bad
            // relation eager-load, a corrupted Redis cache value inside
            // StudentSignOutLock, or a missing column on a half-deployed
            // schema used to take the whole dashboard to a 500 because
            // a thrown exception inside a View::composer bubbles all the
            // way up. Wrap each step so the layout renders even when one
            // piece misbehaves; exceptions still go to laravel.log via
            // report() so we can diagnose them after the fact.
            $sid = session('student_id');

            $student = null;
            if ($sid) {
                try {
                    $student = Student::query()
                        ->with(['department.faculty', 'schoolClass'])
                        ->find($sid);
                } catch (\Throwable $e) {
                    report($e);
                    try {
                        $student = Student::query()->find($sid);
                    } catch (\Throwable $e2) {
                        report($e2);
                        $student = null;
                    }
                }
            }

            if ($view->name() === 'layouts.classrep') {
                $view->with('repStudent', $student);
            } elseif (! $view->offsetExists('student')) {
                $view->with('student', $student);
            }

            $signOutBlocked = false;
            $signOutBlockMessage = null;
            if ($student) {
                try {
                    $signOutBlocked = \App\Support\StudentSignOutLock::isSignOutBlocked($student);
                    $signOutBlockMessage = $signOutBlocked
                        ? \App\Support\StudentSignOutLock::blockMessage($student)
                        : null;
                } catch (\Throwable $e) {
                    report($e);
                    $signOutBlocked = false;
                    $signOutBlockMessage = null;
                }
            }
            $view->with([
                'studentSignOutBlocked' => $signOutBlocked,
                'studentSignOutBlockMessage' => $signOutBlockMessage,
            ]);
        });

        View::composer('layouts.admin', function ($view) {
            if ($view->offsetExists('dashboardRole')) {
                return;
            }
            $view->with('dashboardRole', 'admin');
            if (session()->has('admin_id') && ! $view->offsetExists('user')) {
                $view->with('user', User::find(session('admin_id')));
            }
        });
    }

    /**
     * Hard-fail the boot when production .env is missing critical
     * security or performance settings. Each branch prints the EXACT
     * remediation command the operator should run. Errors are
     * accumulated so the operator sees every problem in a single
     * boot, not one at a time.
     *
     * Only invoked when:
     *   - app()->environment('production') === true
     *   - app()->runningInConsole()        === false
     * which means artisan commands can always boot — that is the only
     * escape hatch when an env var is wrong (php artisan config:cache,
     * qr:rotate-secret, migrate, queue:work, tinker).
     *
     * Touches NO database, NO schema, NO cache. Pure config() reads.
     */
    private function assertProductionEnvironment(): void
    {
        $errors = [];

        if ((string) config('qr.secret') === '') {
            $errors[] = 'QR_SECRET is missing or empty. Run: php artisan qr:rotate-secret';
        }

        if ((string) config('database.default') === 'sqlite') {
            $errors[] = 'DB_CONNECTION=sqlite is unsafe in production. Set DB_CONNECTION=mysql in .env and migrate.';
        }

        if ((string) config('cache.default') === 'database') {
            $errors[] = 'CACHE_STORE=database is too slow for production. Set CACHE_STORE=file (or redis) in .env.';
        }

        $cors = (array) config('cors.allowed_origins', []);
        foreach ($cors as $origin) {
            $o = strtolower((string) $origin);
            if (str_contains($o, 'localhost')
                || str_contains($o, '127.0.0.1')
                || str_contains($o, '0.0.0.0')) {
                $errors[] = sprintf(
                    'CORS_ALLOWED_ORIGINS contains a dev origin (%s). Remove before production deploy.',
                    $origin
                );
                break;
            }
        }

        if ((int) config('sanctum.expiration', 0) <= 0) {
            // Not fatal but log a warning so the operator notices.
            // The Log facade may not be wired this early in some boot
            // paths — swallow rather than crash on the warning.
            try {
                \Log::warning('SANCTUM_TOKEN_EXPIRATION is not set in production. Set to 10080 (one week) for token rotation.');
            } catch (\Throwable $e) {
                // logger not yet booted — ignore
            }
        }

        if ($errors !== []) {
            throw new \RuntimeException(
                "Production environment guard failed:\n  - ".implode("\n  - ", $errors)
            );
        }
    }
}
