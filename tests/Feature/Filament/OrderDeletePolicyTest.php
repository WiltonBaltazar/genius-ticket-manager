<?php

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;
use Livewire\Livewire;

it('actually releases a pending order\'s quantity when Delete is invoked through the admin panel', function () {
    $staff = Staff::factory()->superAdmin()->create();
    $ticketType = TicketType::factory()->create(['total_quantity' => 10, 'available_quantity' => 9]);
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1]);

    Livewire::actingAs($staff, 'staff')
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('delete');

    expect($order->fresh()->trashed())->toBeTrue()
        ->and($ticketType->fresh()->available_quantity)->toBe(10);
});
