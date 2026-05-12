<?php

declare(strict_types=1);

namespace Tests\Feature\Alert;

use App\Models\CaseImportantDate;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\Event;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportantDateAlertTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $user;
    protected User $otherTenantUser;
    protected Client $client;
    protected CaseType $caseType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('admin');

        $this->otherTenantUser = User::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $this->otherTenantUser->assignRole('admin');

        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->caseType = CaseType::first() ?? CaseType::factory()->create();
    }

    private function createCase(?Tenant $tenant = null, ?Client $client = null): ImmigrationCase
    {
        $tenant = $tenant ?? $this->tenant;
        $client = $client ?? ($tenant->id === $this->tenant->id
            ? $this->client
            : Client::factory()->create(['tenant_id' => $tenant->id]));

        return ImmigrationCase::factory()->create([
            'tenant_id'    => $tenant->id,
            'client_id'    => $client->id,
            'case_type_id' => $this->caseType->id,
        ]);
    }

    public function test_returns_dates_within_window(): void
    {
        $case = $this->createCase();

        $inside = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(120)->toDateString(),
        ]);

        CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->subDays(120)->toDateString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/important-date-alerts');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($inside->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_tenant_isolation(): void
    {
        $myCase = $this->createCase();
        $myDate = CaseImportantDate::factory()->create([
            'case_id'  => $myCase->id,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $otherCase = $this->createCase($this->otherTenant);
        $otherDate = CaseImportantDate::factory()->create([
            'case_id'  => $otherCase->id,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/important-date-alerts');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($myDate->id, $ids);
        $this->assertNotContains($otherDate->id, $ids);
    }

    public function test_urgency_computed_correctly(): void
    {
        $case = $this->createCase();

        $overdueCritical = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->subDays(15)->toDateString(),
        ]);
        $overdueRecent = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
        $todayDate = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->toDateString(),
        ]);
        $upcomingWeek = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);
        $upcomingMonth = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(20)->toDateString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/important-date-alerts?per_page=100&sort_by=due_date&sort_dir=asc');

        $response->assertStatus(200);

        $byId = collect($response->json('data'))->keyBy('id');

        $this->assertSame('overdue_critical', $byId[$overdueCritical->id]['urgency_bucket']);
        $this->assertSame(1, $byId[$overdueCritical->id]['urgency_level']);

        $this->assertSame('overdue_recent', $byId[$overdueRecent->id]['urgency_bucket']);
        $this->assertSame(2, $byId[$overdueRecent->id]['urgency_level']);

        $this->assertSame('today', $byId[$todayDate->id]['urgency_bucket']);
        $this->assertSame(3, $byId[$todayDate->id]['urgency_level']);

        $this->assertSame('upcoming_week', $byId[$upcomingWeek->id]['urgency_bucket']);
        $this->assertSame(4, $byId[$upcomingWeek->id]['urgency_level']);

        $this->assertSame('upcoming_month', $byId[$upcomingMonth->id]['urgency_bucket']);
        $this->assertSame(5, $byId[$upcomingMonth->id]['urgency_level']);
    }

    public function test_filter_by_urgency_overdue(): void
    {
        $case = $this->createCase();

        $overdue = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
        $upcoming = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/important-date-alerts?urgency=overdue');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
    }

    public function test_sort_by_due_date_asc(): void
    {
        $case = $this->createCase();

        $later = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        $earlier = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/important-date-alerts?sort_by=due_date&sort_dir=asc');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $earlierPos = array_search($earlier->id, $ids, true);
        $laterPos   = array_search($later->id, $ids, true);
        $this->assertNotFalse($earlierPos);
        $this->assertNotFalse($laterPos);
        $this->assertLessThan($laterPos, $earlierPos);
    }

    public function test_link_event_stores_relation(): void
    {
        $case = $this->createCase();
        $date = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $event = Event::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/important-date-alerts/{$date->id}/link-event", [
                'event_id' => $event->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('case_important_dates', [
            'id'                => $date->id,
            'calendar_event_id' => $event->id,
        ]);
        $response->assertJsonPath('data.has_calendar_event', true);
        $response->assertJsonPath('data.calendar_event_id', $event->id);
    }

    public function test_link_event_rejects_other_tenant_event(): void
    {
        $case = $this->createCase();
        $date = CaseImportantDate::factory()->create([
            'case_id'  => $case->id,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $foreignEvent = Event::factory()->create([
            'tenant_id'  => $this->otherTenant->id,
            'created_by' => $this->otherTenantUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/important-date-alerts/{$date->id}/link-event", [
                'event_id' => $foreignEvent->id,
            ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('case_important_dates', [
            'id'                => $date->id,
            'calendar_event_id' => null,
        ]);
    }
}
