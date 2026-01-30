<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_request_requires_valid_email(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_request_for_nonexistent_email_fails(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new_password123', $user->password));
    }

    public function test_user_cannot_reset_password_with_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_reset_password_with_mismatched_passwords(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'new_password123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_verify_token_returns_valid_for_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->getJson("/api/verify-token/{$token}/test@example.com");

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    public function test_verify_token_returns_invalid_for_nonexistent_user(): void
    {
        $response = $this->getJson('/api/verify-token/some-token/nonexistent@example.com');

        $response->assertStatus(404)
            ->assertJson(['valid' => false]);
    }

    public function test_verify_token_returns_invalid_for_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->getJson('/api/verify-token/invalid-token/test@example.com');

        $response->assertStatus(422)
            ->assertJson(['valid' => false]);
    }

    public function test_password_reset_requires_all_fields(): void
    {
        $response = $this->postJson('/api/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
