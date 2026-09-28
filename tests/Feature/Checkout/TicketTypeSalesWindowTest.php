<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Str;

function salesWindowPayload(Event $event, TicketType ...$ticketTypes): array
{
    return [
        'transaction_hash' => (string) Str::uuid(),
        'event_id' => $event->id,
        'items' => array_map(
            fn (TicketType $ticketType) => ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
            $ticketTypes,
        ),
        'name' => 'Jane Attendee',
        'email' => 'jane@example.test',
        'phone' => '+258840000000',
    ];
}

it('rejects an order for a ticket type whose sales window has ended', function () {
    $event = Event::factory()->create();
    $earlyBird = TicketType::factory()->for($event)->create([
        'sales_start_date' => now()->subMonth(),
        'sales_end_date' => now()->subMinute(),
    ]);

    $response = $this->postJson('/checkout', salesWindowPayload($event, $earlyBird));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.ticket_type_id']);
    expect(Order::count())->toBe(0)
        ->and($earlyBird->fresh()->available_quantity)->toBe($earlyBird->available_quantity);
});

it('rejects an order for a ticket type whose sales have not started yet', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'sales_start_date' => now()->addDay(),
        'sales_end_date' => now()->addMonth(),
    ]);

    $response = $this->postJson('/checkout', salesWindowPayload($event, $ticketType));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.ticket_type_id']);
    expect(Order::count())->toBe(0);
});

it('keeps selling the event\'s other ticket types after one type\'s sales window ends', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create([
        'sales_start_date' => now()->subMonth(),
        'sales_end_date' => now()->subMinute(),
    ]);
    $normal = TicketType::factory()->for($event)->create([
        'sales_start_date' => now()->subMonth(),
        'sales_end_date' => null,
    ]);

    $response = $this->postJson('/checkout', salesWindowPayload($event, $normal));

    $response->assertCreated();
});

it('rejects the whole order when any one of its items is off sale', function () {
    $event = Event::factory()->create();
    $expired = TicketType::factory()->for($event)->create(['sales_end_date' => now()->subMinute()]);
    $onSale = TicketType::factory()->for($event)->create(['sales_start_date' => null, 'sales_end_date' => null]);

    $response = $this->postJson('/checkout', salesWindowPayload($event, $onSale, $expired));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.1.ticket_type_id']);
    expect(Order::count())->toBe(0)
        ->and($onSale->fresh()->available_quantity)->toBe($onSale->available_quantity);
});
