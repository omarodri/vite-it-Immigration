<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_own_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'profile',
            ]);
    }

    public function test_user_can_update_name(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_update_profile_info(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'phone' => '+1234567890',
                'city' => 'New York',
                'country' => 'USA',
                'bio' => 'This is my bio',
                'timezone' => 'America/New_York',
                'language' => 'en',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully',
            ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->user->id,
            'phone' => '+1234567890',
            'city' => 'New York',
            'country' => 'USA',
            'bio' => 'This is my bio',
            'timezone' => 'America/New_York',
            'language' => 'en',
        ]);
    }

    public function test_user_can_update_social_links(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'social_links' => [
                    'twitter' => 'https://twitter.com/username',
                    'linkedin' => 'https://linkedin.com/in/username',
                ],
            ]);

        $response->assertStatus(200);

        $profile = $this->user->fresh()->profile;
        $this->assertNotNull($profile);
        $this->assertEquals('https://twitter.com/username', $profile->social_links['twitter']);
        $this->assertEquals('https://linkedin.com/in/username', $profile->social_links['linkedin']);
    }

    public function test_profile_validation_fails_with_invalid_timezone(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'timezone' => 'Invalid/Timezone',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_profile_validation_fails_with_invalid_website(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'website' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['website']);
    }

    public function test_profile_validation_fails_with_future_date_of_birth(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'date_of_birth' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_user_can_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($this->user)
            ->postJson('/api/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'avatar_url',
                'profile',
            ]);

        $this->assertNotNull($this->user->fresh()->profile->avatar_url);
        Storage::disk('public')->assertExists('avatars/'.$file->hashName());
    }

    public function test_avatar_upload_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg')->size(3000); // 3MB

        $response = $this->actingAs($this->user)
            ->postJson('/api/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_user_can_delete_avatar(): void
    {
        // First upload an avatar
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->actingAs($this->user)
            ->postJson('/api/profile/avatar', ['avatar' => $file]);

        $avatarPath = 'avatars/'.$file->hashName();
        Storage::disk('public')->assertExists($avatarPath);

        // Then delete it
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/profile/avatar');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Avatar deleted successfully',
            ]);

        Storage::disk('public')->assertMissing($avatarPath);
        $this->assertNull($this->user->fresh()->profile->avatar_url);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password changed successfully',
            ]);

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_password_change_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_fails_without_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/profile/password', [
                'current_password' => 'current-password',
                'password' => 'new-password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $response = $this->putJson('/api/profile', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_includes_roles(): void
    {
        // Create role first
        \Spatie\Permission\Models\Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $this->user->assignRole('test-role');

        $response = $this->actingAs($this->user)
            ->getJson('/api/profile');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('user.roles'));
    }

    public function test_updating_profile_creates_profile_if_not_exists(): void
    {
        // Ensure no profile exists
        $this->assertNull($this->user->profile);

        $response = $this->actingAs($this->user)
            ->putJson('/api/profile', [
                'city' => 'Los Angeles',
            ]);

        $response->assertStatus(200);
        $this->assertNotNull($this->user->fresh()->profile);
        $this->assertEquals('Los Angeles', $this->user->fresh()->profile->city);
    }
}
