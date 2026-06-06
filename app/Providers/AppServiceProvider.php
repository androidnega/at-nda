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
}
