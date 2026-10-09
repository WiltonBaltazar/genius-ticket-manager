<?php

namespace App\Actions\Events;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Collection;

class BuildEventReportAction
{
    /**
     * Everything the event report PDF shows, as plain arrays/numbers so the
     * view only formats. Money is summed from order item subtotals for this
     * event's ticket types — never orders.total_amount — since an order has no
     * event_id and could, in principle, carry items for another event too.
     *
     * "Sold" means quantity on paid orders; pending orders still hold stock
     * (TicketType::available_quantity) but aren't revenue yet.
     *
     * @return array<string, mixed>
     */
    public function handle(Event $event): array
    {
        $ticketTypes = $event->ticketTypes()->orderBy('price')->orderBy('name')->get();

        /** @var Collection<int, OrderItem> $items */
        $items = OrderItem::query()
            ->whereIn('ticket_type_id', $ticketTypes->modelKeys())
            ->with('order')
            ->get();

        $paidItems = $items->filter(fn (OrderItem $item) => $item->order->status === OrderStatus::Paid);

        /** @var Collection<int, Ticket> $tickets */
        $tickets = Ticket::query()
            ->whereIn('ticket_type_id', $ticketTypes->modelKeys())
            ->get();

        return [
            'summary' => $this->summary($ticketTypes, $paidItems),
            'ticketTypes' => $this->perTicketType($ticketTypes, $paidItems),
            'salesByDay' => $this->salesByDay($paidItems),
            'ordersByStatus' => $this->ordersByStatus($items),
            'revenueByPaymentMethod' => $this->revenueByPaymentMethod($paidItems),
            'checkInByTicketType' => $this->checkIns($tickets, fn (Ticket $ticket) => $ticket->ticket_type_id, $ticketTypes->pluck('name', 'id')->all()),
            'checkInByDate' => $tickets->contains(fn (Ticket $ticket) => $ticket->event_date !== null)
                ? $this->checkIns($tickets->sortBy('event_date'), fn (Ticket $ticket) => $ticket->event_date?->toDateString() ?? '—')
                : [],
            'checkInTotal' => $this->checkInCounts($tickets),
        ];
    }

    /**
     * @param  Collection<int, TicketType>  $ticketTypes
     * @param  Collection<int, OrderItem>  $paidItems
     */
    private function summary(Collection $ticketTypes, Collection $paidItems): array
    {
        $revenue = (float) $paidItems->sum('subtotal');
        $sold = (int) $paidItems->sum('quantity');
        $capacity = (int) $ticketTypes->sum('total_quantity');
        $paidOrders = $paidItems->pluck('order_id')->unique()->count();

        return [
            'revenue' => $revenue,
            'ticketsSold' => $sold,
            'capacity' => $capacity,
            'capacityUsedPercent' => $capacity > 0 ? round($sold / $capacity * 100, 1) : 0.0,
            'paidOrders' => $paidOrders,
            'averageOrderValue' => $paidOrders > 0 ? round($revenue / $paidOrders, 2) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, TicketType>  $ticketTypes
     * @param  Collection<int, OrderItem>  $paidItems
     */
    private function perTicketType(Collection $ticketTypes, Collection $paidItems): array
    {
        $paidByType = $paidItems->groupBy('ticket_type_id');

        return $ticketTypes->map(function (TicketType $ticketType) use ($paidByType) {
            $typeItems = $paidByType->get($ticketType->id, collect());

            return [
                'name' => $ticketType->name,
                'price' => (float) $ticketType->price,
                'capacity' => (int) $ticketType->total_quantity,
                'sold' => (int) $typeItems->sum('quantity'),
                'available' => (int) $ticketType->available_quantity,
                'revenue' => (float) $typeItems->sum('subtotal'),
                'salesStart' => $ticketType->sales_start_date?->toDateString(),
                'salesEnd' => $ticketType->sales_end_date?->toDateString(),
            ];
        })->values()->all();
    }

    /**
     * Keyed by the day the payment was confirmed (falling back to when the
     * order was placed, for paid orders confirmed before confirmed_at existed).
     *
     * @param  Collection<int, OrderItem>  $paidItems
     */
    private function salesByDay(Collection $paidItems): array
    {
        $cumulativeTickets = 0;
        $cumulativeRevenue = 0.0;

        return $paidItems
            ->groupBy(fn (OrderItem $item) => ($item->order->confirmed_at ?? $item->order->created_at)->toDateString())
            ->sortKeys()
            ->map(function (Collection $dayItems, string $date) use (&$cumulativeTickets, &$cumulativeRevenue) {
                $tickets = (int) $dayItems->sum('quantity');
                $revenue = (float) $dayItems->sum('subtotal');
                $cumulativeTickets += $tickets;
                $cumulativeRevenue += $revenue;

                return [
                    'date' => $date,
                    'tickets' => $tickets,
                    'revenue' => $revenue,
                    'cumulativeTickets' => $cumulativeTickets,
                    'cumulativeRevenue' => $cumulativeRevenue,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Every status listed (zeros included) so the table always reads the same.
     *
     * @param  Collection<int, OrderItem>  $items
     */
    private function ordersByStatus(Collection $items): array
    {
        $byStatus = $items->groupBy(fn (OrderItem $item) => $item->order->status->value);

        return collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'orders' => $byStatus->get($status->value, collect())->pluck('order_id')->unique()->count(),
            'amount' => (float) $byStatus->get($status->value, collect())->sum('subtotal'),
        ])->all();
    }

    /**
     * @param  Collection<int, OrderItem>  $paidItems
     */
    private function revenueByPaymentMethod(Collection $paidItems): array
    {
        $byMethod = $paidItems->groupBy(fn (OrderItem $item) => $item->order->payment_method?->value);

        return collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
            'method' => $method->value,
            'orders' => $byMethod->get($method->value, collect())->pluck('order_id')->unique()->count(),
            'revenue' => (float) $byMethod->get($method->value, collect())->sum('subtotal'),
        ])->all();
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<string, string>  $labels  group key => display label
     */
    private function checkIns(Collection $tickets, callable $groupBy, array $labels = []): array
    {
        return $tickets
            ->groupBy($groupBy)
            ->map(fn (Collection $group, string $key) => ['label' => $labels[$key] ?? $key] + $this->checkInCounts($group))
            ->values()
            ->all();
    }

    /**
     * Voided tickets (refunds, cancellations) aren't expected at the door, so
     * the check-in rate is over valid tickets only.
     *
     * @param  Collection<int, Ticket>  $tickets
     */
    private function checkInCounts(Collection $tickets): array
    {
        $checkedIn = $tickets->where('status', TicketStatus::CheckedIn)->count();
        $unused = $tickets->where('status', TicketStatus::Unused)->count();
        $voided = $tickets->where('status', TicketStatus::Voided)->count();
        $valid = $checkedIn + $unused;

        return [
            'issued' => $tickets->count(),
            'checkedIn' => $checkedIn,
            'noShow' => $unused,
            'voided' => $voided,
            'rate' => $valid > 0 ? round($checkedIn / $valid * 100, 1) : 0.0,
        ];
    }
}
