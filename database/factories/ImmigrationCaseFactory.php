<?php

namespace Database\Factories;

use App\Models\CaseType;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImmigrationCase>
 */
class ImmigrationCaseFactory extends Factory
{
    protected $model = ImmigrationCase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([
            ImmigrationCase::STATUS_ACTIVE,
            ImmigrationCase::STATUS_INACTIVE,
            ImmigrationCase::STATUS_ARCHIVED,
            ImmigrationCase::STATUS_CLOSED,
        ]);

        $isClosed = $status === ImmigrationCase::STATUS_CLOSED;

        return [
            'tenant_id' => Tenant::factory(),
            'case_number' => $this->generateCaseNumber(),
            'client_id' => Client::factory(),
            'case_type_id' => CaseType::factory(),
            'assigned_to' => null,
            'status' => $status,
            'priority' => fake()->randomElement([
                ImmigrationCase::PRIORITY_URGENT,
                ImmigrationCase::PRIORITY_HIGH,
                ImmigrationCase::PRIORITY_MEDIUM,
                ImmigrationCase::PRIORITY_LOW,
            ]),
            'progress' => fake()->numberBetween(0, 100),
            'language' => fake()->randomElement(['es', 'en', 'fr']),
            'description' => fake()->optional()->paragraph(),
            'hearing_date' => fake()->optional(0.6)->dateTimeBetween('+1 week', '+1 year'),
            'fda_deadline' => fake()->optional(0.4)->dateTimeBetween('+1 week', '+6 months'),
            'brown_sheet_date' => fake()->optional(0.3)->dateTimeBetween('-6 months', 'now'),
            'evidence_deadline' => fake()->optional(0.4)->dateTimeBetween('+1 week', '+3 months'),
            'archive_box_number' => $isClosed ? fake()->bothify('BOX-###') : null,
            'closed_at' => $isClosed ? fake()->dateTimeBetween('-1 year', 'now') : null,
            'closure_notes' => $isClosed ? fake()->sentence() : null,
        ];
    }

    /**
     * Generate a unique case number.
     */
    private function generateCaseNumber(): string
    {
        $year = date('Y');
        $code = fake()->randomElement(['ASYLUM', 'WORK', 'STUDENT', 'EXPRESS_ENTRY', 'PEQ', 'TOURIST']);
        $sequence = fake()->unique()->numberBetween(1, 99999);

        return sprintf('%s-%s-%05d', $year, $code, $sequence);
    }

    /**
     * Indicate that the case is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImmigrationCase::STATUS_ACTIVE,
            'closed_at' => null,
            'closure_notes' => null,
        ]);
    }

    /**
     * Indicate that the case is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImmigrationCase::STATUS_INACTIVE,
            'closed_at' => null,
            'closure_notes' => null,
        ]);
    }

    /**
     * Indicate that the case is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImmigrationCase::STATUS_CLOSED,
            'closed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'closure_notes' => fake()->sentence(),
            'archive_box_number' => fake()->bothify('BOX-###'),
        ]);
    }

    /**
     * Indicate that the case is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImmigrationCase::STATUS_ARCHIVED,
            'archive_box_number' => fake()->bothify('BOX-###'),
        ]);
    }

    /**
     * Indicate that the case is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ImmigrationCase::PRIORITY_URGENT,
        ]);
    }

    /**
     * Indicate that the case has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => ImmigrationCase::PRIORITY_HIGH,
        ]);
    }

    /**
     * Indicate that the case has a hearing date.
     */
    public function withHearing(int $daysFromNow = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'hearing_date' => now()->addDays($daysFromNow),
        ]);
    }

    /**
     * Indicate that the case belongs to a specific client.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
        ]);
    }

    /**
     * Indicate that the case is assigned to a specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * Indicate that the case uses a specific case type.
     */
    public function withCaseType(CaseType $caseType): static
    {
        return $this->state(fn (array $attributes) => [
            'case_type_id' => $caseType->id,
        ]);
    }

    /**
     * Indicate that the case is unassigned.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => null,
        ]);
    }
}
