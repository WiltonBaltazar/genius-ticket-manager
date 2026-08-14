<?php

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\SubmitOrderAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\SubmitOrderRequest;
use App\Models\Order;
use App\Notifications\Orders\OrderStatusLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * Dual-purpose, same pattern as EventCheckoutController::show — SPA shell
     * for a browser navigation (e.g. the emailed order-status link), JSON for
     * the app's own fetch() call to the same URL.
     */
    public function show(Request $request, Order $order): Response
    {
        if (! $request->expectsJson()) {
            return response(view('app'));
        }

        $order->load(['orderItems.ticketType', 'tickets']);

        $payload = [
            'id' => $order->id,
            'status' => $order->status->value,
            'total_amount' => (string) $order->total_amount,
            'payment_method' => $order->payment_method?->value,
            'created_at' => $order->created_at,
            'proof_of_payment_uploaded' => $order->proof_of_payment_path !== null,
            'items' => $order->orderItems->map(fn ($item) => [
                'ticket_type_name' => $item->ticketType->name,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
            ]),
            'tickets' => $order->status === OrderStatus::Paid
                ? $order->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'pdf_url' => "/orders/{$order->id}/tickets/{$ticket->id}/pdf",
                ])
                : [],
        ];

        // expires_at is omitted entirely (not set to null) for a non-pending order —
        // its presence/absence is the signal the frontend and tests key off.
        if ($order->status === OrderStatus::Pending) {
            $payload['expires_at'] = $order->created_at->copy()->addHours(24);
        }

        return response()->json(['order' => $payload]);
    }

    public function store(SubmitOrderRequest $request, SubmitOrderAction $action): JsonResponse
    {
        $attendee = $request->user('web');

        $result = $action->handle([
            'transaction_hash' => $request->string('transaction_hash')->toString(),
            'event_id' => $request->string('event_id')->toString(),
            'items' => $request->input('items'),
            'name' => $attendee ? null : $request->input('name'),
            'email' => $attendee ? null : $request->input('email'),
            'phone' => $attendee ? null : $request->input('phone'),
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
