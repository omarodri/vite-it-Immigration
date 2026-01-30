<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->google2fa = new Google2FA();

        // Enable session and set proper headers for Sanctum stateful requests
        $this->withSession([]);
        $this->withHeaders([
            'Referer' => 'http://localhost',
        ]);
    }

    public function test_user_can_enable_two_factor(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/two-factor/enable');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'qr_code',
                'secret',
                'recovery_codes',
            ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_secret);
        $this->assertNotNull($this->user->two_factor_recovery_codes);
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_user_can_confirm_two_factor_with_valid_code(): void
    {
        // Enable two-factor
        $response = $this->actingAs($this->user)
            ->postJson('/api/two-factor/enable');

        $secret = $response->json('secret');
        $validCode = $this->google2fa->getCurrentOtp($secret);

        // Confirm with valid code
        $response = $this->actingAs($this->user)
            ->postJson('/api/two-factor/confirm', [
                'code' => $validCode,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Two-factor authentication confirmed successfully.',
            ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_confirmed_at);
    }

    public function test_user_cannot_confirm_with_invalid_code(): void
    {
        // Enable two-factor
        $this->actingAs($this->user)
            ->postJson('/api/two-factor/enable');

        // Try to confirm with invalid code
        $response = $this->actingAs($this->user)
            ->postJson('/api/two-factor/confirm', [
                'code' => '000000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_user_can_disable_two_factor(): void
    {
        // Create user with two-factor enabled
        $secret = $this->google2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = ['CODE1', 'CODE2'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/two-factor/disable', [
                'current_password' => 'password123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Two-factor authentication disabled successfully.',
            ]);

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_recovery_codes);
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_user_cannot_disable_without_password(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/two-factor/disable');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_user_cannot_disable_with_wrong_password(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/two-factor/disable', [
                'current_password' => 'wrongpassword',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_user_can_get_recovery_codes(): void
    {
        $recoveryCodes = ['CODE1', 'CODE2', 'CODE3'];
        $this->user->two_factor_recovery_codes = $recoveryCodes;
        $this->user->save();
        $this->user->refresh();

        $response = $this->actingAs($this->user)
            ->getJson('/api/two-factor/recovery-codes');

        $response->assertStatus(200)
            ->assertJson([
                'recovery_codes' => $recoveryCodes,
            ]);
    }

    public function test_user_can_regenerate_recovery_codes(): void
    {
        $oldCodes = ['CODE1', 'CODE2'];
        $this->user->two_factor_recovery_codes = $oldCodes;
        $this->user->save();

        $response = $this->actingAs($this->user)
            ->postJson('/api/two-factor/recovery-codes', [
                'current_password' => 'password123',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recovery_codes',
                'message',
            ]);

        $newCodes = $response->json('recovery_codes');
        $this->assertCount(8, $newCodes);
        $this->assertNotEquals($oldCodes, $newCodes);
    }

    public function test_login_returns_two_factor_required_when_enabled(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = ['CODE1'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();
        $this->user->refresh();

        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Two-factor authentication required',
                'two_factor_required' => true,
            ]);

        $this->assertGuest();
    }

    public function test_two_factor_challenge_succeeds_with_valid_code(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = ['CODE1'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();

        // Login to set up session
        $loginResponse = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200);

        $validCode = $this->google2fa->getCurrentOtp($secret);

        $response = $this->withSession([
            'two_factor_user_id' => $this->user->id,
            'two_factor_login_at' => now()->timestamp,
        ])->postJson('/api/two-factor-challenge', [
            'code' => $validCode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful',
            ])
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_two_factor_challenge_fails_with_invalid_code(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = ['CODE1'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();

        // Login to set up session
        $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response = $this->withSession([
            'two_factor_user_id' => $this->user->id,
            'two_factor_login_at' => now()->timestamp,
        ])->postJson('/api/two-factor-challenge', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertGuest();
    }

    public function test_two_factor_challenge_succeeds_with_recovery_code(): void
    {
        $recoveryCode = 'TESTCODE12';
        $secret = $this->google2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = [$recoveryCode, 'CODE2'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();

        // Login to set up session
        $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response = $this->withSession([
            'two_factor_user_id' => $this->user->id,
            'two_factor_login_at' => now()->timestamp,
        ])->postJson('/api/two-factor-challenge', [
            'recovery_code' => $recoveryCode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful',
            ]);

        $this->assertAuthenticatedAs($this->user);

        // Verify recovery code was consumed
        $this->user->refresh();
        $this->assertNotContains($recoveryCode, $this->user->two_factor_recovery_codes);
        $this->assertCount(1, $this->user->two_factor_recovery_codes);
    }

    public function test_two_factor_challenge_fails_with_expired_session(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = ['CODE1'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();

        // Manually set expired session
        $this->withSession([
            'two_factor_user_id' => $this->user->id,
            'two_factor_login_at' => now()->subMinutes(6)->timestamp,
        ]);

        $validCode = $this->google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/two-factor-challenge', [
            'code' => $validCode,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertGuest();
    }

    public function test_two_factor_challenge_fails_without_session(): void
    {
        $response = $this->postJson('/api/two-factor-challenge', [
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertGuest();
    }

    public function test_login_without_two_factor_works_normally(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful',
            ])
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_unauthenticated_cannot_access_two_factor_settings(): void
    {
        $response = $this->postJson('/api/two-factor/enable');

        $response->assertStatus(401);
    }
}
