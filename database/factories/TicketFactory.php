<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'ticket_type_id' => TicketType::factory(),
            'qr_code' => Str::random(48),
            'status' => TicketStatus::Unused,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Voided,
        ]);
    }
}
