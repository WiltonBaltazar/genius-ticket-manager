<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;
use Livewire\Livewire;

it('allows refund for the same roles already permitted to view orders', function () {
    $order = Order::factory()->create(); // Paid by default

    foreach (['superAdmin', 'eventManager', 'support'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('refund', $order))->toBeTrue();
    }
});

it('refuses refund for gate_operator', function () {
    $order = Order::factory()->create();
    $staff = Staff::factory()->gateOperator()->create();

    expect($staff->can('refund', $order))->toBeFalse();
});

it('shows the Refund action on a paid order in the admin panel for a permitted role', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->create();

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Refund');
});

it('does not show the Refund action on a pending order', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create();

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertDontSee('Refund');
});

it('actually refunds the order when the Refund action is invoked through the admin panel', function () {
    $staff = Staff::factory()->eventManager()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 9]);
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    Livewire::actingAs($staff, 'staff')
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('refund');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});
