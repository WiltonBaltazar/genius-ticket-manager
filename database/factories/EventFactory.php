<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+6 months');

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'venue' => fake()->address(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $startDate->format('Y-m-d'),
            'status' => EventStatus::Published,
        ];
    }

    public function twoDay(): static
    {
        return $this->state(function (array $attributes) {
            $start = \Carbon\Carbon::parse($attributes['start_date']);

            return [
                'end_date' => $start->copy()->addDay()->format('Y-m-d'),
            ];
        });
    }
}
