<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function sendResetLink(array $data): string
    {
        return Password::sendResetLink($data);
    }

    public function resetPassword(array $data): string
    {
        return Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );
    }

    public function verifyToken(string $token, string $email): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ['valid' => false, 'message' => 'User not found.', 'status' => 404];
        }

        $tokenExists = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$tokenExists) {
            return ['valid' => false, 'message' => 'Invalid or expired token.', 'status' => 422];
        }

        if (!Hash::check($token, $tokenExists->token)) {
            return ['valid' => false, 'message' => 'Invalid token.', 'status' => 422];
        }

        return ['valid' => true, 'message' => 'Token is valid.', 'status' => 200];
    }
}
