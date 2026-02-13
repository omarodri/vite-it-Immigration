<?php

namespace Tests\Feature;

use App\Models\CaseType;
use App\Models\Client;
use App\Models\Companion;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $adminUser;

    protected User $consultorUser;

    protected User $regularUser;

    protected Client $client;

    protected CaseType $caseType;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for roles/permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create tenants
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        // Create users
        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser->assignRole('admin');

        $this->consultorUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->consultorUser->assignRole('consultor');

        $this->regularUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->regularUser->assignRole('cliente');

        // Create client and case type
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->caseType = CaseType::first() ?? CaseType::factory()->create();
    }

    // ==================== List Cases ====================

    public function test_admin_can_list_cases(): void
    {
        ImmigrationCase::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_cases_are_paginated(): void
    {
        ImmigrationCase::factory()->count(20)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_can_filter_by_status(): void
    {
        ImmigrationCase::factory()->count(2)->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);
        ImmigrationCase::factory()->count(3)->closed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases?status=active');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_by_priority(): void
    {
        ImmigrationCase::factory()->count(2)->urgent()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);
        ImmigrationCase::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases?priority=urgent');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_by_case_type(): void
    {
        $otherCaseType = CaseType::factory()->create();

        ImmigrationCase::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);
        ImmigrationCase::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $otherCaseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases?case_type_id={$this->caseType->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_by_assignee(): void
    {
        ImmigrationCase::factory()->count(2)->assignedTo($this->consultorUser)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);
        ImmigrationCase::factory()->count(3)->unassigned()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases?assigned_to={$this->consultorUser->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_cases(): void
    {
        ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
            'case_number' => '2026-ASYLUM-00001',
        ]);
        ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
            'case_number' => '2026-WORK-00002',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases?search=ASYLUM');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_number', '2026-ASYLUM-00001');
    }

    public function test_unauthorized_user_cannot_list_cases(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/cases');

        $response->assertStatus(403);
    }

    public function test_user_only_sees_own_tenant_cases(): void
    {
        ImmigrationCase::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $otherClient = Client::factory()->create(['tenant_id' => $this->otherTenant->id]);
        ImmigrationCase::factory()->count(3)->create([
            'tenant_id' => $this->otherTenant->id,
            'client_id' => $otherClient->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ==================== Create Case ====================

    public function test_admin_can_create_case(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'priority' => 'high',
                'description' => 'Test case description',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.case_type_id', $this->caseType->id)
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('cases', [
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);
    }

    public function test_case_number_is_generated_automatically(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        $response->assertStatus(201);

        $caseNumber = $response->json('data.case_number');
        $this->assertNotNull($caseNumber);
        $this->assertMatchesRegularExpression('/^\d{4}-[A-Z_]+-\d{5}$/', $caseNumber);
    }

    public function test_create_case_requires_client_id(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'case_type_id' => $this->caseType->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_create_case_requires_case_type_id(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['case_type_id']);
    }

    public function test_cannot_create_case_with_client_from_another_tenant(): void
    {
        $otherClient = Client::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $otherClient->id,
                'case_type_id' => $this->caseType->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_unauthorized_user_cannot_create_case(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_case_auto_assigned_to_user_tenant(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cases', [
            'id' => $response->json('data.id'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ==================== View Case ====================

    public function test_admin_can_view_case(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.case_number', $case->case_number);
    }

    public function test_case_includes_relations(): void
    {
        $case = ImmigrationCase::factory()->assignedTo($this->consultorUser)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'client' => ['id', 'first_name', 'last_name'],
                    'case_type' => ['id', 'name', 'code'],
                    'assigned_user' => ['id', 'name'],
                ],
            ]);
    }

    public function test_cannot_view_case_from_another_tenant(): void
    {
        $otherClient = Client::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'client_id' => $otherClient->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases/{$case->id}");

        // Returns 404 because the BelongsToTenant global scope filters out
        // records from other tenants - this is secure behavior (no information leakage)
        $response->assertStatus(404);
    }

    // ==================== Update Case ====================

    public function test_admin_can_update_case(): void
    {
        $case = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/cases/{$case->id}", [
                'priority' => 'urgent',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.description', 'Updated description');
    }

    public function test_can_update_case_status(): void
    {
        $case = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/cases/{$case->id}", [
                'status' => 'inactive',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_can_update_case_progress(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
            'progress' => 0,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/cases/{$case->id}", [
                'progress' => 75,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.progress', 75)
            ->assertJsonPath('data.progress_percentage', '75%');
    }

    public function test_closure_notes_required_when_closing(): void
    {
        $case = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/cases/{$case->id}", [
                'status' => 'closed',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['closure_notes']);
    }

    public function test_unauthorized_user_cannot_update_case(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->putJson("/api/cases/{$case->id}", [
                'priority' => 'urgent',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Delete Case ====================

    public function test_admin_can_delete_case(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Case deleted successfully.');

        $this->assertSoftDeleted('cases', ['id' => $case->id]);
    }

    public function test_case_is_soft_deleted(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/cases/{$case->id}");

        $this->assertNotNull($case->fresh()->deleted_at);
    }

    public function test_unauthorized_user_cannot_delete_case(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->deleteJson("/api/cases/{$case->id}");

        $response->assertStatus(403);
    }

    // ==================== Assign Case ====================

    public function test_admin_can_assign_case(): void
    {
        $case = ImmigrationCase::factory()->unassigned()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/cases/{$case->id}/assign", [
                'assigned_to' => $this->consultorUser->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_to', $this->consultorUser->id)
            ->assertJsonPath('data.assigned_user.id', $this->consultorUser->id);
    }

    public function test_cannot_assign_to_user_from_another_tenant(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/cases/{$case->id}/assign", [
                'assigned_to' => $otherUser->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_unauthorized_user_cannot_assign_case(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/cases/{$case->id}/assign", [
                'assigned_to' => $this->consultorUser->id,
            ]);

        $response->assertStatus(403);
    }

    // ==================== Timeline ====================

    public function test_can_get_case_timeline(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases/{$case->id}/timeline");

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ==================== Statistics ====================

    public function test_can_get_case_statistics(): void
    {
        ImmigrationCase::factory()->count(2)->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);
        ImmigrationCase::factory()->count(1)->urgent()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/cases/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'by_status' => ['active', 'inactive', 'archived', 'closed'],
                    'by_priority' => ['urgent', 'high', 'medium', 'low'],
                    'upcoming_hearings',
                    'unassigned',
                ],
            ]);
    }

    // ==================== Case Types ====================

    public function test_can_list_case_types(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/case-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'category'],
                ],
            ]);
    }

    public function test_can_view_case_type(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/case-types/{$this->caseType->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->caseType->id);
    }

    // ==================== Activity Logging ====================

    public function test_creating_case_logs_activity(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ImmigrationCase::class,
            'subject_id' => $response->json('data.id'),
            'causer_id' => $this->adminUser->id,
            'log_name' => 'default',
        ]);
    }

    public function test_updating_case_logs_activity(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/cases/{$case->id}", [
                'priority' => 'urgent',
            ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ImmigrationCase::class,
            'subject_id' => $case->id,
            'causer_id' => $this->adminUser->id,
        ]);
    }

    // ==================== Companions ====================

    public function test_can_create_case_with_companions(): void
    {
        $companions = Companion::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'companion_ids' => $companions->pluck('id')->toArray(),
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.companions');

        $this->assertDatabaseCount('case_companions', 2);
    }

    public function test_case_show_includes_companions(): void
    {
        $case = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $companions = Companion::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $case->companions()->sync($companions->pluck('id'));

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.companions');
    }

    public function test_cannot_assign_companions_from_different_client(): void
    {
        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $companions = Companion::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'companion_ids' => $companions->pluck('id')->toArray(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['companion_ids']);
    }

    public function test_cannot_assign_companions_from_different_tenant(): void
    {
        $otherClient = Client::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $companions = Companion::factory()->count(2)->create([
            'tenant_id' => $this->otherTenant->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'companion_ids' => $companions->pluck('id')->toArray(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['companion_ids']);
    }

    public function test_can_create_case_with_assigned_user(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'assigned_to' => $this->consultorUser->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.assigned_to', $this->consultorUser->id)
            ->assertJsonPath('data.assigned_user.id', $this->consultorUser->id);
    }

    public function test_cannot_assign_case_to_user_from_different_tenant(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'assigned_to' => $otherUser->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    // ==================== Staff Endpoint ====================

    public function test_can_get_staff_members(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users/staff');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email'],
                ],
            ]);

        // Should include all users from the tenant (admin, consultor, regular)
        $this->assertCount(3, $response->json('data'));
    }

    public function test_staff_endpoint_only_returns_tenant_users(): void
    {
        // Create user in other tenant
        User::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users/staff');

        $response->assertStatus(200);

        // Should only include users from the current tenant (3 users)
        $this->assertCount(3, $response->json('data'));
    }
}
