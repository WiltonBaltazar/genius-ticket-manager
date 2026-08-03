<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
{
    protected $model = PaymentEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'event_type' => fake()->randomElement(['authorized', 'captured', 'failed', 'refunded']),
            'payload' => ['id' => 'evt_'.fake()->uuid(), 'raw' => fake()->sentence()],
            'occurred_at' => now(),
        ];
    }
}
