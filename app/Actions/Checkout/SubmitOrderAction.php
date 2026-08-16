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
     * @param  array{transaction_hash: string, event_id: string, items: array<int, array{ticket_type_id: string, quantity: int, event_date?: ?string}>, name?: ?string, email?: ?string, phone?: ?string, attendee?: ?Attendee, ip_address?: ?string, user_agent?: ?string}  $data
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
                    $ticketType = TicketType::with('event')->where('id', $item['ticket_type_id'])->first();

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

                    $eventDate = $item['event_date'] ?? null;
                    // A single day of a multi-day event costs its even share of the full
                    // price — validated as in-range and only allowed on multi-day events
                    // by SubmitOrderRequest::withValidator() before this ever runs.
                    $unitPrice = $eventDate !== null
                        ? round($ticketType->price / $ticketType->event->daysCount(), 2)
                        : $ticketType->price;

                    $lineItems[] = [
                        'ticketType' => $ticketType,
                        'quantity' => $item['quantity'],
                        'eventDate' => $eventDate,
                        'unitPrice' => $unitPrice,
                    ];
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
                        fn (array $lineItem) => $lineItem['unitPrice'] * $lineItem['quantity']
                    ),
                    'ip_address' => $data['ip_address'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                ]);

                foreach ($lineItems as $lineItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'ticket_type_id' => $lineItem['ticketType']->id,
                        'event_date' => $lineItem['eventDate'],
                        'quantity' => $lineItem['quantity'],
                        'unit_price' => $lineItem['unitPrice'],
                        'subtotal' => $lineItem['unitPrice'] * $lineItem['quantity'],
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
