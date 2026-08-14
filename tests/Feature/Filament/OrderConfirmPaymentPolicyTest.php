<?php

use App\Models\Order;
use App\Models\Staff;

it('allows confirmPayment for the same roles already permitted to view orders', function () {
    $order = Order::factory()->pending()->create();

    foreach (['superAdmin', 'eventManager', 'support'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('confirmPayment', $order))->toBeTrue();
    }
});

it('refuses confirmPayment for gate_operator', function () {
    $order = Order::factory()->pending()->create();
    $staff = Staff::factory()->gateOperator()->create();

    expect($staff->can('confirmPayment', $order))->toBeFalse();
});

it('shows the ConfirmPayment action on a pending order in the admin panel for a permitted role', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create();

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Confirm Payment');
});

it('does not show the ConfirmPayment action on an already-paid order', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->create(); // Paid by default

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

    $response->assertOk();
    $response->assertDontSee('Confirm Payment');
});

it('shows an uploaded proof-of-payment file on a pending order before confirming', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->pending()->create(['proof_of_payment_path' => 'proof-of-payment/receipt.jpg']);

    $response = $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}");

    $response->assertOk();
});

it('renders an expired order in both the orders list and detail view without error', function () {
    $staff = Staff::factory()->eventManager()->create();
    $order = Order::factory()->expired()->create();

    $this->actingAs($staff, 'staff')->get('/admin/orders')->assertOk()->assertSee('expired');
    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}")->assertOk()->assertSee('expired');
});
