<?php

namespace Tests\Feature\Api;

use App\Models\InvitationCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create(['name' => 'Invitation Tenant']);
        $this->tenantAdmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->tenantAdmin->assignRole('admin');
    }

    /**
     * Helper to create an invitation code with required created_by field.
     */
    private function createInvitationCode(array $attributes = []): InvitationCode
    {
        return InvitationCode::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->tenantAdmin->id,
            'uses_remaining' => 5,
            'expires_at' => now()->addDays(7),
        ], $attributes));
    }

    public function test_registration_with_valid_invitation_code(): void
    {
        // Arrange
        $this->createInvitationCode(['code' => 'VALID-CODE-123']);

        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'VALID-CODE-123',
        ]);

        // Assert
        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->tenant->id, $user->tenant_id);
    }

    public function test_registration_without_invitation_code_fails(): void
    {
        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_registration_with_invalid_code_fails(): void
    {
        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'NONEXISTENT-CODE',
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_registration_with_expired_code_fails(): void
    {
        // Arrange
        $this->createInvitationCode([
            'code' => 'EXPIRED-CODE',
            'expires_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'EXPIRED-CODE',
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_registration_with_exhausted_code_fails(): void
    {
        // Arrange
        $this->createInvitationCode([
            'code' => 'EXHAUSTED-CODE',
            'uses_remaining' => 0,
        ]);

        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'EXHAUSTED-CODE',
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation_code']);
    }

    public function test_invitation_code_decrements_on_use(): void
    {
        // Arrange
        $code = $this->createInvitationCode([
            'code' => 'DECREMENT-CODE',
            'uses_remaining' => 3,
        ]);

        // Act
        $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'DECREMENT-CODE',
        ]);

        // Assert
        $code->refresh();
        $this->assertEquals(2, $code->uses_remaining);
    }

    public function test_email_restricted_code_works_for_correct_email(): void
    {
        // Arrange
        $this->createInvitationCode([
            'code' => 'EMAIL-RESTRICTED',
            'email' => 'specific@example.com',
            'uses_remaining' => 1,
        ]);

        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Specific User',
            'email' => 'specific@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'EMAIL-RESTRICTED',
        ]);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'specific@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_email_restricted_code_fails_for_wrong_email(): void
    {
        // Arrange
        $this->createInvitationCode([
            'code' => 'EMAIL-RESTRICTED-2',
            'email' => 'specific@example.com',
            'uses_remaining' => 1,
        ]);

        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Wrong User',
            'email' => 'wrong@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_code' => 'EMAIL-RESTRICTED-2',
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['invitation_code']);
    }
}
