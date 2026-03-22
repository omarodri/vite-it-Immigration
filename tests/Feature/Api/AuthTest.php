<?php

namespace Tests\Feature\Api;

use App\Models\InvitationCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable session for Sanctum SPA tests
        $this->withSession([]);

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        // Create the 'user' role required by AuthService::register()
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }

    /**
     * Create a valid invitation code for testing.
     */
    private function createInvitationCode(array $overrides = []): InvitationCode
    {
        $creator = User::factory()->create(['tenant_id' => $this->tenant->id]);

        return InvitationCode::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => 'VALID-CODE-' . uniqid(),
            'email' => null,
            'uses_remaining' => 1,
            'expires_at' => null,
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_user_can_register_with_valid_invitation_code(): void
    {
        $invitation = $this->createInvitationCode(['code' => 'WELCOME2024']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'WELCOME2024',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify uses_remaining was decremented
        $this->assertDatabaseHas('invitation_codes', [
            'id' => $invitation->id,
            'uses_remaining' => 0,
        ]);
    }

    public function test_user_cannot_register_without_invitation_code(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_user_cannot_register_with_invalid_invitation_code(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'NONEXISTENT-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_user_cannot_register_with_expired_invitation_code(): void
    {
        $this->createInvitationCode([
            'code' => 'EXPIRED-CODE',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'EXPIRED-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_user_cannot_register_with_exhausted_invitation_code(): void
    {
        $this->createInvitationCode([
            'code' => 'USED-CODE',
            'uses_remaining' => 0,
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'USED-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_user_cannot_register_with_email_restricted_code_and_wrong_email(): void
    {
        $this->createInvitationCode([
            'code' => 'RESTRICTED-CODE',
            'email' => 'specific@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'wrong@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'RESTRICTED-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_user_can_register_with_email_restricted_code_and_correct_email(): void
    {
        $this->createInvitationCode([
            'code' => 'RESTRICTED-CODE',
            'email' => 'specific@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'specific@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'RESTRICTED-CODE',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'specific@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_registration_assigns_correct_tenant_from_invitation_code(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'is_active' => true,
        ]);

        $creator = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $invitation = InvitationCode::create([
            'tenant_id' => $otherTenant->id,
            'code' => 'OTHER-TENANT-CODE',
            'uses_remaining' => 1,
            'created_by' => $creator->id,
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'OTHER-TENANT-CODE',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'tenant_id' => $otherTenant->id,
        ]);
    }

    public function test_user_cannot_register_with_invalid_email(): void
    {
        $this->createInvitationCode(['code' => 'VALID-CODE']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'VALID-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);
        $this->createInvitationCode(['code' => 'VALID-CODE']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_code' => 'VALID-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_with_mismatched_passwords(): void
    {
        $this->createInvitationCode(['code' => 'VALID-CODE']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
            'invitation_code' => 'VALID-CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_failed_login_is_logged(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'test@example.com',
            'successful' => false,
        ]);
    }

    public function test_successful_login_is_logged(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'test@example.com',
            'successful' => true,
        ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_current_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_unauthenticated_user_cannot_get_current_user(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_login_returns_validation_error_for_missing_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_register_returns_validation_error_for_missing_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'invitation_code']);
    }
}
