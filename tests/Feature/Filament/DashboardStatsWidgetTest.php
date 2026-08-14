<?php

use App\Enums\OrderStatus;
use App\Filament\Widgets\OrderStatsOverview;
use App\Models\Order;
use App\Models\Staff;
use Livewire\Livewire;

it('shows dashboard totals that match the underlying order data', function () {
    Order::factory()->count(2)->create(['status' => OrderStatus::Paid, 'total_amount' => 100]);
    Order::factory()->count(3)->create(['status' => OrderStatus::Pending]);
    Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $staff = Staff::factory()->superAdmin()->create();

    $component = Livewire::actingAs($staff, 'staff')->test(OrderStatsOverview::class);

    $component->assertSee('6')   // total orders
        ->assertSee('2')          // paid orders
        ->assertSee('200.00')     // revenue
        ->assertSee('3');         // pending orders
});

it('does not render the widget for gate_operator', function () {
    $staff = Staff::factory()->gateOperator()->create();

    $this->actingAs($staff, 'staff');

    expect(OrderStatsOverview::canView())->toBeFalse();

    $this->get('/admin')->assertDontSee('Total Orders');
});
