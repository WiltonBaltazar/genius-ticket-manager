<?php

use App\Actions\Orders\OrderNotPaidException;
use App\Actions\Orders\ResendOrderTicketsAction;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Staff;
use App\Notifications\Orders\OrderConfirmed;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('allows resending tickets for the same roles already permitted to view orders', function () {
    $order = Order::factory()->create(); // Paid by default

    foreach (['superAdmin', 'eventManager', 'support'] as $factoryState) {
        $staff = Staff::factory()->{$factoryState}()->create();
        expect($staff->can('resendTickets', $order))->toBeTrue();
    }
});

it('refuses resending tickets for gate_operator', function () {
    $order = Order::factory()->create();
    $staff = Staff::factory()->gateOperator()->create();

    expect($staff->can('resendTickets', $order))->toBeFalse();
});

it('shows the Resend Tickets action on a paid order', function () {
    $staff = Staff::factory()->support()->create();
    $order = Order::factory()->create();

    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Resend Tickets');
});

it('does not show the Resend Tickets action on a pending order', function () {
    $staff = Staff::factory()->support()->create();
    $order = Order::factory()->pending()->create();

    $this->actingAs($staff, 'staff')->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertDontSee('Resend Tickets');
});

it('re-sends the tickets email to the attendee and logs who resent it', function () {
    Notification::fake();
    $staff = Staff::factory()->support()->create();
    $order = Order::factory()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('resendTickets');

    Notification::assertSentTo($order->attendee, OrderConfirmed::class);
    expect($order->auditLogs()->where('action', 'order.tickets_resent')->first())
        ->staff_id->toBe($staff->id);
});

it('re-sends to a different email when one is given, without changing the attendee\'s email', function () {
    Notification::fake();
    $staff = Staff::factory()->support()->create();
    $order = Order::factory()->create();
    $originalEmail = $order->attendee->email;

    Livewire::actingAs($staff, 'staff')
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('resendTickets', data: ['email' => 'corrected@example.test'])
        ->assertHasNoActionErrors();

    Notification::assertSentOnDemand(
        OrderConfirmed::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'corrected@example.test',
    );
    Notification::assertNotSentTo($order->attendee, OrderConfirmed::class);
    expect($order->attendee->fresh()->email)->toBe($originalEmail)
        ->and($order->auditLogs()->where('action', 'order.tickets_resent')->first()->changes)
        ->toBe(['email' => 'corrected@example.test']);
});

it('rejects an invalid alternative email', function () {
    Notification::fake();
    $staff = Staff::factory()->support()->create();
    $order = Order::factory()->create();

    Livewire::actingAs($staff, 'staff')
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->callAction('resendTickets', data: ['email' => 'not-an-email'])
        ->assertHasActionErrors(['email']);

    Notification::assertNothingSent();
});

it('renders the tickets email with the attendee\'s name when sent to a bare address', function () {
    $order = Order::factory()->create();

    $html = (new OrderConfirmed($order))
        ->toMail(Notification::route('mail', 'corrected@example.test'))
        ->render();

    expect((string) $html)->toContain(e($order->attendee->name));
});

it('refuses to resend tickets for an order that is not paid', function () {
    Notification::fake();
    $order = Order::factory()->pending()->create();

    expect(fn () => app(ResendOrderTicketsAction::class)->handle($order, Staff::factory()->support()->create()))
        ->toThrow(OrderNotPaidException::class);

    Notification::assertNothingSent();
});
