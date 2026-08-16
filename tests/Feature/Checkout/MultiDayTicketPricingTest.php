<?php

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;
use Illuminate\Support\Str;

function multiDayCheckoutPayload(Event $event, TicketType $ticketType, array $itemOverrides = []): array
{
    return [
        'transaction_hash' => (string) Str::uuid(),
        'event_id' => $event->id,
        'items' => [
            array_merge(['ticket_type_id' => $ticketType->id, 'quantity' => 2], $itemOverrides),
        ],
        'name' => 'Jane Attendee',
        'email' => 'jane@example.test',
        'phone' => '+258840000000',
    ];
}

it('divides the price by the number of days when a single day of a multi-day event is selected', function () {
    $event = Event::factory()->twoDay()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'price' => 100,
        'total_quantity' => 10,
        'available_quantity' => 10,
    ]);

    $response = $this->postJson('/checkout', multiDayCheckoutPayload($event, $ticketType, [
        'event_date' => $event->start_date->toDateString(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('order.total_amount', '100.00'); // 2 x (100 / 2 days)

    $item = Order::find($response->json('order.id'))->orderItems->first();
    expect($item->unit_price)->toEqual('50.00')
        ->and($item->event_date->toDateString())->toBe($event->start_date->toDateString());
});

it('still sells the full multi-day pass at full price when no day is selected', function () {
    $event = Event::factory()->twoDay()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'price' => 100,
        'total_quantity' => 10,
        'available_quantity' => 10,
    ]);

    $response = $this->postJson('/checkout', multiDayCheckoutPayload($event, $ticketType));

    $response->assertCreated();
    $response->assertJsonPath('order.total_amount', '200.00');

    $item = Order::find($response->json('order.id'))->orderItems->first();
    expect($item->unit_price)->toEqual('100.00')
        ->and($item->event_date)->toBeNull();
});

it('rejects a day selection for a single-day event', function () {
    $event = Event::factory()->create(); // single day by default
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);

    $response = $this->postJson('/checkout', multiDayCheckoutPayload($event, $ticketType, [
        'event_date' => $event->start_date->toDateString(),
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.event_date']);
    expect(Order::count())->toBe(0);
});

it('rejects a day selection outside the event\'s date range', function () {
    $event = Event::factory()->twoDay()->create();
    $ticketType = TicketType::factory()->for($event)->create(['total_quantity' => 10, 'available_quantity' => 10]);
    $outsideDay = $event->end_date->copy()->addDay()->toDateString();

    $response = $this->postJson('/checkout', multiDayCheckoutPayload($event, $ticketType, [
        'event_date' => $outsideDay,
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.event_date']);
    expect(Order::count())->toBe(0);
});

it('draws single-day and full-pass selections of the same ticket type from one shared availability pool', function () {
    $event = Event::factory()->twoDay()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'total_quantity' => 3,
        'available_quantity' => 3,
    ]);

    // 2 full-pass + a request for 2 single-day (only 1 unit left) should fail entirely.
    $response = $this->postJson('/checkout', [
        'transaction_hash' => (string) Str::uuid(),
        'event_id' => $event->id,
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2, 'event_date' => $event->start_date->toDateString()],
        ],
        'name' => 'Jane Attendee',
        'email' => 'jane-pool@example.test',
        'phone' => '+258840000000',
    ]);

    $response->assertUnprocessable();
    expect($ticketType->fresh()->available_quantity)->toBe(3)
        ->and(Order::count())->toBe(0);
});

it('denormalizes event_date onto tickets issued from a single-day order item', function () {
    $event = Event::factory()->twoDay()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->pending()->create();
    $dayTwo = $event->end_date->toDateString();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'event_date' => $dayTwo,
        'quantity' => 1,
    ]);

    $staff = Staff::factory()->eventManager()->create();
    $confirmed = app(ConfirmOrderPaymentAction::class)->handle($order, $staff);

    expect($confirmed->tickets->first()->event_date->toDateString())->toBe($dayTwo);
});
