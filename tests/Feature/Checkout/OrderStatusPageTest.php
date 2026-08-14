<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Support\Str;

it('returns the order shape with expires_at only while pending', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id]);

    $response = $this->getJson("/orders/{$order->id}");

    $response->assertOk();
    $response->assertJsonPath('order.id', $order->id);
    $response->assertJsonPath('order.status', 'pending');
    $response->assertJsonStructure(['order' => ['expires_at']]);
    $response->assertJsonPath('order.tickets', []);
});

it('does not include expires_at once an order is paid', function () {
    $order = Order::factory()->create(); // default factory state is Paid

    $response = $this->getJson("/orders/{$order->id}");

    $response->assertOk();
    $response->assertJsonMissingPath('order.expires_at');
});

it('returns 404 for a nonexistent order id', function () {
    $this->getJson('/orders/'.Str::uuid())->assertNotFound();
});

it('exposes no endpoint that lists or enumerates orders for an unauthenticated caller', function () {
    // The only order-fetching route this feature registers is GET /orders/{order},
    // which requires the specific UUID — there's no GET /orders (list) route at all.
    $this->getJson('/orders')->assertNotFound();
});
