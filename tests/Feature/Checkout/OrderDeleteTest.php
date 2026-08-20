<?php

use App\Actions\Orders\DeleteOrderAction;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketType;

it('releases a pending order\'s reserved quantity back when deleted (40 -> 39 -> 40)', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 40, 'available_quantity' => 39]);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    app(DeleteOrderAction::class)->handle($order, $staff);

    expect($ticketType->fresh()->available_quantity)->toBe(40)
        ->and($order->fresh()->trashed())->toBeTrue();
});

it('releases a paid order\'s quantity and voids its tickets when deleted directly, without a prior refund', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 40, 'available_quantity' => 37]);
    $order = Order::factory()->create(); // Paid by default
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3]);
    Ticket::factory()->count(3)->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $ticketType->id]);

    app(DeleteOrderAction::class)->handle($order, $staff);

    expect($ticketType->fresh()->available_quantity)->toBe(40)
        ->and($order->fresh()->trashed())->toBeTrue()
        ->and($order->tickets()->get()->every(fn (Ticket $ticket) => $ticket->status === TicketStatus::Voided))->toBeTrue();
});

it('does not release quantity a second time for an order already refunded', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 10]);
    $order = Order::factory()->refunded()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3]);

    app(DeleteOrderAction::class)->handle($order, $staff);

    expect($ticketType->fresh()->available_quantity)->toBe(10);
});

it('does not release quantity a second time for an order already expired', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::Expired]);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3]);

    app(DeleteOrderAction::class)->handle($order, $staff);

    expect($ticketType->fresh()->available_quantity)->toBe(10);
});

it('writes an AuditLog row with the deleting staff attributed', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 9]);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    app(DeleteOrderAction::class)->handle($order, $staff);

    $log = $order->auditLogs()->latest()->first();
    expect($log->staff_id)->toBe($staff->id)
        ->and($log->action)->toBe('order.deleted');
});
