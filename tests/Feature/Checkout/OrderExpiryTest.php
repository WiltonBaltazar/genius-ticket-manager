<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;

it('expires a pending order older than 24h and releases its reserved inventory', function () {
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 7]);
    $order = Order::factory()->pending()->create(['created_at' => now()->subHours(25)]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 3,
    ]);

    $this->artisan('orders:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($ticketType->fresh()->available_quantity)->toBe(10);
});

it('leaves a pending order younger than 24h untouched', function () {
    $order = Order::factory()->pending()->create(['created_at' => now()->subHours(2)]);

    $this->artisan('orders:expire-pending')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('writes an AuditLog row for each expired order', function () {
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 7]);
    $order = Order::factory()->pending()->create(['created_at' => now()->subHours(25)]);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3]);

    $this->artisan('orders:expire-pending');

    expect($order->auditLogs()->first())->not->toBeNull();
});

// "A subsequent payment-confirmation attempt on an expired order is refused" (FR-012,
// FR-017) is covered by tests/Feature/Checkout/PaymentConfirmationTest.php (US4, Phase
// 6) alongside the other non-pending refusal cases, rather than here — ConfirmOrderPaymentAction
// doesn't exist yet at this point in the build.
