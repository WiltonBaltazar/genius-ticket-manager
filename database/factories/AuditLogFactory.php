<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'action' => fake()->randomElement(['order.refunded', 'ticket.checked_in', 'event.published']),
            'auditable_type' => Order::class,
            'auditable_id' => (string) Str::uuid7(),
            'changes' => ['note' => fake()->sentence()],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
