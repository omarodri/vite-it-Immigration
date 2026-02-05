<?php

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TwoFactorService $twoFactorService
    ) {}

    public function register(array $data): User
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('User registered');

        return $user;
    }

    public function login(Request $request): array
    {
        $email = $request->input('email');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        if (! Auth::attempt($request->only('email', 'password'))) {
            LoginAttempt::log($email, $ipAddress, $userAgent, false, 'Invalid credentials');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        LoginAttempt::log($email, $ipAddress, $userAgent, true);

        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->put('two_factor_user_id', $user->id);
                $request->session()->put('two_factor_login_at', now()->timestamp);
                $request->session()->regenerate();
            }

            return ['two_factor_required' => true];
        }

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $ipAddress])
            ->log('User logged in');

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ['user' => $this->loadUserWithPermissions($user)];
    }

    public function twoFactorChallenge(Request $request): User
    {
        if (! $request->hasSession()) {
            throw ValidationException::withMessages([
                'code' => ['Session not available.'],
            ]);
        }

        $userId = $request->session()->get('two_factor_user_id');
        $loginAt = $request->session()->get('two_factor_login_at');

        if (! $userId || ! $loginAt) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication session not found.'],
            ]);
        }

        if (now()->timestamp - $loginAt > 300) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_login_at']);
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication session has expired.'],
            ]);
        }

        $user = $this->userRepository->findById($userId);

        if (! $user) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_login_at']);
            throw ValidationException::withMessages([
                'code' => ['User not found.'],
            ]);
        }

        $isValid = false;

        if ($request->filled('code')) {
            $isValid = $this->twoFactorService->verifyCode($user->two_factor_secret, $request->code);
        } elseif ($request->filled('recovery_code')) {
            $isValid = $this->twoFactorService->verifyRecoveryCode($user, $request->recovery_code);
        }

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        $request->session()->forget(['two_factor_user_id', 'two_factor_login_at']);

        Auth::login($user);
        $request->session()->regenerate();

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip(), 'method' => $request->filled('code') ? 'totp' : 'recovery_code'])
            ->log('User logged in via 2FA');

        return $this->loadUserWithPermissions($user);
    }

    public function logout(Request $request): void
    {
        $user = Auth::user();

        if ($user) {
            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties(['ip' => $request->ip()])
                ->log('User logged out');
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function getAuthenticatedUser(User $user): User
    {
        return $this->loadUserWithPermissions($user);
    }

    private function loadUserWithPermissions(User $user): User
    {
        $user->load(['roles.permissions', 'profile']);
        $user->permissions = $user->roles
            ->flatMap->permissions
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        return $user;
    }
}
