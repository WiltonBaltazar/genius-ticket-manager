<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendee_id' => Attendee::factory(),
            'status' => OrderStatus::Paid,
            'transaction_hash' => Str::uuid()->toString(),
            'stripe_payment_intent_id' => 'pi_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'total_amount' => fake()->randomFloat(2, 20, 1000),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded,
            'refunded_at' => now(),
        ]);
    }
}
