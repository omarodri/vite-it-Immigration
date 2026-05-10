<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Companion;
use App\Models\Event;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TimeLog;
use App\Models\Todo;
use App\Models\WorkflowStage;
use App\Observers\CaseObserver;
use App\Observers\ClientObserver;
use App\Observers\CompanionObserver;
use App\Observers\EventSyncObserver;
use App\Observers\ImmigrationCaseObserver;
use App\Observers\TaskWorkflowObserver;
use App\Observers\TimeLogObserver;
use App\Observers\TodoObserver;
use App\Policies\TaskTemplatePolicy;
use App\Policies\WorkflowStagePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Client::observe(ClientObserver::class);
        Companion::observe(CompanionObserver::class);
        Event::observe(EventSyncObserver::class);
        ImmigrationCase::observe(CaseObserver::class);
        ImmigrationCase::observe(ImmigrationCaseObserver::class);
        Task::observe(TaskWorkflowObserver::class);
        TimeLog::observe(TimeLogObserver::class);
        Todo::observe(TodoObserver::class);

        Gate::policy(WorkflowStage::class, WorkflowStagePolicy::class);
        Gate::policy(TaskTemplate::class, TaskTemplatePolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        $config = config('rate-limiting');

        RateLimiter::for('login', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['login']['decay_minutes'],
                $config['login']['max_attempts']
            )->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts. Please try again later.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });

        RateLimiter::for('register', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['register']['decay_minutes'],
                $config['register']['max_attempts']
            )->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many registration attempts. Please try again later.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });

        RateLimiter::for('password-reset', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['password_reset']['decay_minutes'],
                $config['password_reset']['max_attempts']
            )->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many password reset attempts. Please try again later.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });

        RateLimiter::for('api', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['api']['decay_minutes'],
                $config['api']['max_attempts']
            )->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many requests. Please slow down.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });

        RateLimiter::for('email-verification', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['email_verification']['decay_minutes'],
                $config['email_verification']['max_attempts']
            )->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many verification email requests. Please wait.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });

        RateLimiter::for('uploads', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['uploads']['decay_minutes'],
                $config['uploads']['max_attempts']
            )->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many upload requests. Please wait before uploading more files.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });

        RateLimiter::for('downloads', function (Request $request) use ($config) {
            return Limit::perMinutes(
                $config['downloads']['decay_minutes'],
                $config['downloads']['max_attempts']
            )->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many download requests. Please wait before downloading more files.',
                    'error' => 'rate_limit_exceeded',
                ], 429);
            });
        });
    }
}
