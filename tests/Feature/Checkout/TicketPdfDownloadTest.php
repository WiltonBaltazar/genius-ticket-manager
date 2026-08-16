<?php

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Str;

function paidOrderWithOneTicket(): array
{
    $event = Event::factory()->create(['name' => 'Annual Gala']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP Pass']);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    $staff = Staff::factory()->eventManager()->create();
    $confirmed = app(ConfirmOrderPaymentAction::class)->handle($order, $staff);

    return [$confirmed, $confirmed->tickets->first()];
}

it('downloads a PDF for a ticket on a paid order', function () {
    [$order, $ticket] = paidOrderWithOneTicket();

    $response = $this->get("/orders/{$order->id}/tickets/{$ticket->id}/pdf");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(100);
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('downloads a PDF for a single-day ticket on a multi-day event', function () {
    $event = Event::factory()->twoDay()->create(['name' => 'Annual Gala']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP Pass']);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'event_date' => $event->end_date->toDateString(),
        'quantity' => 1,
    ]);

    $staff = Staff::factory()->eventManager()->create();
    $confirmed = app(ConfirmOrderPaymentAction::class)->handle($order, $staff);
    $ticket = $confirmed->tickets->first();

    $response = $this->get("/orders/{$confirmed->id}/tickets/{$ticket->id}/pdf");

    $response->assertOk();
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('returns 404 for a ticket on a pending order', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->pending()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id]);
    $ticket = Ticket::factory()->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $ticketType->id]);

    $this->get("/orders/{$order->id}/tickets/{$ticket->id}/pdf")->assertNotFound();
});

it('returns 404 when the ticket does not belong to the given order', function () {
    [$orderA] = paidOrderWithOneTicket();
    [, $ticketB] = paidOrderWithOneTicket();

    $this->get("/orders/{$orderA->id}/tickets/{$ticketB->id}/pdf")->assertNotFound();
});

it('returns 404 for a nonexistent ticket', function () {
    [$order] = paidOrderWithOneTicket();

    $this->get('/orders/'.$order->id.'/tickets/'.Str::uuid().'/pdf')->assertNotFound();
});
