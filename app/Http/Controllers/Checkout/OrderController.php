<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\SubmitOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\SubmitOrderRequest;
use App\Notifications\Orders\OrderStatusLink;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(SubmitOrderRequest $request, SubmitOrderAction $action): JsonResponse
    {
        $attendee = $request->user('web');

        $result = $action->handle([
            'transaction_hash' => $request->string('transaction_hash')->toString(),
            'event_id' => $request->string('event_id')->toString(),
            'items' => $request->input('items'),
            'name' => $attendee ? null : $request->input('name'),
            'email' => $attendee ? null : $request->input('email'),
            'attendee' => $attendee,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($result->order === null) {
            return response()->json([
                'errors' => collect($result->shortfalls)->mapWithKeys(
                    fn (int $available, string $ticketTypeId) => [
                        'items.'.array_search(
                            $ticketTypeId,
                            array_column($request->input('items'), 'ticket_type_id')
                        ).'.quantity' => ["Only {$available} left."],
                    ]
                ),
            ], 422);
        }

        if (! $result->wasAlreadySubmitted) {
            $result->order->attendee->notify(new OrderStatusLink($result->order));
        }

        return response()->json([
            'order' => $this->formatOrder($result->order),
        ], $result->wasAlreadySubmitted ? 200 : 201);
    }

    private function formatOrder($order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'total_amount' => (string) $order->total_amount,
            'items' => $order->orderItems->map(fn ($item) => [
                'ticket_type_id' => $item->ticket_type_id,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
            ]),
        ];
    }
}
