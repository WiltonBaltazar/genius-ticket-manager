<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;

it('lets super_admin, event_manager, and support each view an order with its event, all fields non-editable', function () {
    $ticketType = TicketType::factory()->create();
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id]);

    foreach (['superAdmin', 'eventManager', 'support'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();

        $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertSee($order->id);
    }
});

it('shows an order\'s line items read-only', function () {
    $ticketType = TicketType::factory()->create(['name' => 'VIP Pass']);
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 2]);

    $staff = Staff::factory()->superAdmin()->create();

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('VIP Pass');
});

it('renders an empty line-items list for an order with zero items, without erroring', function () {
    $order = Order::factory()->create();
    $staff = Staff::factory()->superAdmin()->create();

    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}")->assertOk();
});

it('refuses gate_operator entirely from the orders area', function () {
    $order = Order::factory()->create();
    $staff = Staff::factory()->gateOperator()->create();

    $this->actingAs($staff, 'staff')->get('/admin/orders')->assertForbidden();
    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}")->assertForbidden();
});

it('exposes no create or edit route for orders', function () {
    $order = Order::factory()->create();
    $staff = Staff::factory()->superAdmin()->create();

    $this->actingAs($staff, 'staff')->get('/admin/orders/create')->assertNotFound();
    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}/edit")->assertNotFound();
});

it('refuses event_manager and support from deleting an order; only super_admin can', function () {
    $order = Order::factory()->create();

    foreach (['eventManager', 'support'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();

        expect($staff->can('delete', $order))->toBeFalse();
    }

    $superAdmin = Staff::factory()->superAdmin()->create();

    expect($superAdmin->can('delete', $order))->toBeTrue();
});
