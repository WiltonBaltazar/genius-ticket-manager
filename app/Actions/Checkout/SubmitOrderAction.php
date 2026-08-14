<?php

namespace App\Actions\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class SubmitOrderAction
{
    /**
     * @param  array{transaction_hash: string, event_id: string, items: array<int, array{ticket_type_id: string, quantity: int}>, name?: ?string, email?: ?string, phone?: ?string, attendee?: ?Attendee, ip_address?: ?string, user_agent?: ?string}  $data
     */
    public function handle(array $data): SubmitOrderResult
    {
        $existing = Order::where('transaction_hash', $data['transaction_hash'])->first();
        if ($existing) {
            return new SubmitOrderResult($existing, wasAlreadySubmitted: true);
        }

        $attendee = $data['attendee'] ?? Attendee::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'phone' => $data['phone'] ?? null],
        );

        $order = null;

        try {
            DB::transaction(function () use ($data, $attendee, &$order) {
                $lineItems = [];
                $shortfalls = [];

                foreach ($data['items'] as $item) {
                    // Plain read, not lockForUpdate() — research.md §2 deliberately uses
                    // optimistic locking (the conditional UPDATE below), not a pessimistic
                    // row lock, matching the pattern TicketTypeOversellTest already proved.
                    $ticketType = TicketType::where('id', $item['ticket_type_id'])->first();

                    $affected = TicketType::where('id', $ticketType->id)
                        ->where('version', $ticketType->version)
                        ->where('available_quantity', '>=', $item['quantity'])
                        ->update([
                            'available_quantity' => $ticketType->available_quantity - $item['quantity'],
                            'version' => $ticketType->version + 1,
                        ]);

                    if ($affected === 0) {
                        $shortfalls[$item['ticket_type_id']] = $ticketType->fresh()->available_quantity;

                        continue;
                    }

                    $lineItems[] = ['ticketType' => $ticketType, 'quantity' => $item['quantity']];
                }

                if (! empty($shortfalls)) {
                    // Throwing (not returning) is what makes DB::transaction() roll
                    // back every decrement above — an all-or-nothing submission
                    // (research.md §2), not a partial order dropping the short item.
                    throw new InsufficientAvailabilityException($shortfalls);
                }

                $order = Order::create([
                    'attendee_id' => $attendee->id,
                    'status' => OrderStatus::Pending,
                    'transaction_hash' => $data['transaction_hash'],
                    'payment_method' => PaymentMethod::Offline,
                    'total_amount' => collect($lineItems)->sum(
                        fn (array $lineItem) => $lineItem['ticketType']->price * $lineItem['quantity']
                    ),
                    'ip_address' => $data['ip_address'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                ]);

                foreach ($lineItems as $lineItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'ticket_type_id' => $lineItem['ticketType']->id,
                        'quantity' => $lineItem['quantity'],
                        'unit_price' => $lineItem['ticketType']->price,
                        'subtotal' => $lineItem['ticketType']->price * $lineItem['quantity'],
                    ]);
                }

                $order->auditLogs()->create([
                    'staff_id' => null,
                    'action' => 'order.created',
                    'changes' => ['status' => OrderStatus::Pending->value],
                    'ip_address' => $data['ip_address'] ?? null,
                ]);
            });
        } catch (InsufficientAvailabilityException $exception) {
            return new SubmitOrderResult(order: null, shortfalls: $exception->shortfalls);
        }

        return new SubmitOrderResult($order->load('orderItems'));
    }
}
