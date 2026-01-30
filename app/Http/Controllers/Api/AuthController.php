<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
    }

    /**
     * Login user and create session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        if (!Auth::attempt($request->only('email', 'password'))) {
            // Log failed attempt
            LoginAttempt::log(
                $email,
                $ipAddress,
                $userAgent,
                false,
                'Invalid credentials'
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Log successful attempt
        LoginAttempt::log(
            $email,
            $ipAddress,
            $userAgent,
            true
        );

        $user = Auth::user();

        // Check if user has two-factor authentication enabled
        if ($user->hasTwoFactorEnabled()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->put('two_factor_user_id', $user->id);
                $request->session()->put('two_factor_login_at', now()->timestamp);
                $request->session()->regenerate();
            }

            return response()->json([
                'message' => 'Two-factor authentication required',
                'two_factor_required' => true,
            ]);
        }

        // Regenerate session if available (SPA mode)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->load('roles');
        $user->permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
        ]);
    }

    /**
     * Handle two-factor authentication challenge.
     */
    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        if (!$request->hasSession()) {
            throw ValidationException::withMessages([
                'code' => ['Session not available.'],
            ]);
        }

        $userId = $request->session()->get('two_factor_user_id');
        $loginAt = $request->session()->get('two_factor_login_at');

        if (!$userId || !$loginAt) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication session not found.'],
            ]);
        }

        // Check if session has expired (5 minutes)
        if (now()->timestamp - $loginAt > 300) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_login_at']);
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication session has expired.'],
            ]);
        }

        $user = User::find($userId);

        if (!$user) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_login_at']);
            throw ValidationException::withMessages([
                'code' => ['User not found.'],
            ]);
        }

        $twoFactorService = app(TwoFactorService::class);
        $isValid = false;

        if ($request->filled('code')) {
            $isValid = $twoFactorService->verifyCode($user->two_factor_secret, $request->code);
        } elseif ($request->filled('recovery_code')) {
            $isValid = $twoFactorService->verifyRecoveryCode($user, $request->recovery_code);
        }

        if (!$isValid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        // Clear two-factor session data
        $request->session()->forget(['two_factor_user_id', 'two_factor_login_at']);

        // Log the user in
        Auth::login($user);
        $request->session()->regenerate();

        $user->load('roles');
        $user->permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
        ]);
    }

    /**
     * Logout user and invalidate session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // Invalidate session if available (SPA mode)
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles');

        // Add permissions as flat array for frontend
        $user->permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json($user);
    }
}
