<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to user's email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => [
                'email' => [__($status)],
            ],
        ], 422);
    }

    /**
     * Reset user's password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => [
                'email' => [__($status)],
            ],
        ], 422);
    }

    /**
     * Verify if a password reset token is valid.
     */
    public function verifyToken(string $token, string $email): JsonResponse
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'valid' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $tokenExists = \DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$tokenExists) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired token.',
            ], 422);
        }

        // Verify the token hash
        if (!Hash::check($token, $tokenExists->token)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid token.',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token is valid.',
        ]);
    }
}
