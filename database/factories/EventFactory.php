<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-7 days', '+30 days');
        $end   = (clone $start)->modify('+1 hour');

        return [
            'tenant_id'   => Tenant::factory(),
            'created_by'  => User::factory(),
            'title'       => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'start_date'  => $start,
            'end_date'    => $end,
            'all_day'     => false,
            'category'    => $this->faker->randomElement(Event::$categories),
            'sync_source' => 'local',
        ];
    }
}
