<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Str;

function checkoutPayload(Event $event, TicketType $ticketType, array $overrides = []): array
{
    return array_merge([
        'transaction_hash' => (string) Str::uuid(),
        'event_id' => $event->id,
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
        ],
        'name' => 'Jane Attendee',
        'email' => 'jane@example.test',
        'phone' => '+258840000000',
    ], $overrides);
}

it('attaches a logged-in attendee\'s order to their account without needing name/email/phone in the request', function () {
    $attendee = Attendee::factory()->create();
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->actingAs($attendee, 'web')->postJson('/checkout', checkoutPayload($event, $ticketType, [
        'name' => null,
        'email' => null,
        'phone' => null,
    ]));

    $response->assertCreated();
    $order = Order::find($response->json('order.id'));
    expect($order->attendee_id)->toBe($attendee->id);
});

it('requires a phone number for guest checkout', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType, ['phone' => null]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('phone');
});

it('stores the phone number on the created guest attendee', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType, [
        'email' => 'phone-guest@example.test',
        'phone' => '+258849999999',
    ]));

    $response->assertCreated();
    expect(Attendee::where('email', 'phone-guest@example.test')->first()->phone)
        ->toBe('+258849999999');
});

it('creates a guest attendee with no password or verification when checking out unauthenticated', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType, ['email' => 'new-guest@example.test']));

    $response->assertCreated();
    $attendee = Attendee::where('email', 'new-guest@example.test')->first();
    expect($attendee)->not->toBeNull()
        ->and($attendee->password)->toBeNull()
        ->and($attendee->email_verified_at)->toBeNull();
});

it('attaches the order to an existing attendee matched by email rather than duplicating', function () {
    $existing = Attendee::factory()->create(['email' => 'existing@example.test']);
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType, ['email' => 'existing@example.test']));

    $response->assertCreated();
    expect(Order::find($response->json('order.id'))->attendee_id)->toBe($existing->id)
        ->and(Attendee::where('email', 'existing@example.test')->count())->toBe(1);
});

it('creates a pending order with correct totals and decrements available_quantity', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'price' => 100,
        'total_quantity' => 10,
        'available_quantity' => 10,
    ]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType));

    $response->assertCreated();
    $response->assertJsonPath('order.status', 'pending');
    $response->assertJsonPath('order.total_amount', '200.00');

    expect($ticketType->fresh()->available_quantity)->toBe(8);

    $order = Order::find($response->json('order.id'));
    expect($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->quantity)->toBe(2)
        ->and($order->orderItems->first()->subtotal)->toEqual('200.00');
});

it('rejects a line item that exceeds current available_quantity and creates nothing', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 3, 'available_quantity' => 3]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType, [
        'items' => [['ticket_type_id' => $ticketType->id, 'quantity' => 5]],
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.quantity']);
    expect($ticketType->fresh()->available_quantity)->toBe(3)
        ->and(Order::count())->toBe(0);
});

it('writes an AuditLog row for the created order with no staff attribution', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->postJson('/checkout', checkoutPayload($event, $ticketType));

    $order = Order::find($response->json('order.id'));
    $log = $order->auditLogs()->first();
    expect($log)->not->toBeNull()
        ->and($log->staff_id)->toBeNull();
});
