<?php

use App\Actions\Orders\ConfirmOrderPaymentAction;
use App\Actions\Tickets\TransferTicketAction;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
use App\Models\TicketType;
use Livewire\Livewire;

function paidOrderWithTickets(int $quantity = 2): Order
{
    $ticketType = TicketType::factory()->create();
    $order = Order::factory()->pending()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => $quantity]);

    return app(ConfirmOrderPaymentAction::class)->handle($order, Staff::factory()->eventManager()->create());
}

it('shows a transferred ticket\'s new holder on the admin order page', function () {
    $order = paidOrderWithTickets();
    app(TransferTicketAction::class)->handle($order->tickets->first(), 'Nova Pessoa', 'nova@example.test', '+258840000001');

    $staff = Staff::factory()->superAdmin()->create();

    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Nova Pessoa')
        ->assertSee('nova@example.test')
        ->assertSee('+258840000001')
        // The untransferred ticket still shows the buyer as its holder.
        ->assertSee($order->attendee->email);
});

it('finds an order in the admin list by a transferred ticket\'s new holder', function () {
    $transferredOrder = paidOrderWithTickets(1);
    $otherOrder = paidOrderWithTickets(1);
    app(TransferTicketAction::class)->handle($transferredOrder->tickets->first(), 'Nova Pessoa', 'nova@example.test', '+258840000001');

    $this->actingAs(Staff::factory()->superAdmin()->create(), 'staff');

    Livewire::test(ListOrders::class)
        ->searchTable('nova@example.test')
        ->assertCanSeeTableRecords([$transferredOrder])
        ->assertCanNotSeeTableRecords([$otherOrder]);
});
