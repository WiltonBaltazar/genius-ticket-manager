<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalQuantity = fake()->numberBetween(10, 500);

        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['General Admission', 'VIP', 'Early Bird']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 20, 500),
            'total_quantity' => $totalQuantity,
            'available_quantity' => $totalQuantity,
            'version' => 0,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_quantity' => 0,
        ]);
    }
}
