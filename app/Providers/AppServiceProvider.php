<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api-v1', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('api-v1-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        View::composer('layouts.classrep', function ($view) {
            $sid = session('student_id');
            $view->with('repStudent', $sid ? Student::query()->with(['schoolClass', 'department'])->find($sid) : null);
        });

        View::composer('layouts.student', function ($view) {
            if ($view->offsetExists('student')) {
                return;
            }
            $sid = session('student_id');
            $view->with('student', $sid ? Student::query()->with(['department.faculty'])->find($sid) : null);
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
