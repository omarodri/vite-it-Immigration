<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
