<?php

namespace Tests\Feature\Scrum;

use App\Models\ScrumColumn;
use App\Models\ScrumTask;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DT-18 — the board payload must include the assignee's avatar so task cards/rows
 * show the profile picture instead of initials.
 */
class ScrumBoardAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_tasks_include_assignee_avatar_url(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin');

        $withAvatar = User::factory()->create(['tenant_id' => $tenant->id]);
        UserProfile::create(['user_id' => $withAvatar->id, 'avatar_url' => '/storage/avatars/u.jpg']);
        $withoutProfile = User::factory()->create(['tenant_id' => $tenant->id]);

        $column = ScrumColumn::create(['tenant_id' => $tenant->id, 'title' => 'TODO', 'order_index' => 1000]);
        ScrumTask::create(['tenant_id' => $tenant->id, 'scrum_column_id' => $column->id, 'title' => 'A', 'order_index' => 1000, 'assigned_to_id' => $withAvatar->id]);
        ScrumTask::create(['tenant_id' => $tenant->id, 'scrum_column_id' => $column->id, 'title' => 'B', 'order_index' => 2000, 'assigned_to_id' => $withoutProfile->id]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/scrum/board');

        $response->assertOk()
            ->assertJsonPath('data.0.tasks.0.assigned_to.avatar_url', '/storage/avatars/u.jpg')
            ->assertJsonPath('data.0.tasks.1.assigned_to.avatar_url', null)
            ->assertJsonPath('data.0.tasks.1.assigned_to.id', $withoutProfile->id);
    }
}
