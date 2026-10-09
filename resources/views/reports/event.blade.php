@php
    $money = fn (float $amount) => number_format($amount, 2, ',', '.').' MZN';
    $maxDayTickets = max([1, ...array_column($salesByDay, 'tickets')]);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 18mm 15mm; }
        /* DejaVu Sans ships with dompdf and covers the dashes/accents used here. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1f2328; }
        h1 { font-size: 17pt; margin: 0 0 2mm; }
        h2 { font-size: 11.5pt; margin: 7mm 0 2.5mm; padding-bottom: 1mm; border-bottom: 1px solid #d0d7de; }
        .meta { color: #57606a; font-size: 8.5pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.6mm 2mm; text-align: left; border-bottom: 1px solid #eaeef2; }
        th { background: #f6f8fa; font-size: 8pt; text-transform: uppercase; color: #57606a; }
        td.num, th.num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 1px solid #8c959f; }
        .kpis td { width: 33.33%; border: 1px solid #d0d7de; padding: 3mm; vertical-align: top; }
        .kpi-label { font-size: 7.5pt; text-transform: uppercase; color: #57606a; }
        .kpi-value { font-size: 14pt; font-weight: bold; margin-top: 1mm; }
        .bar { height: 3mm; background: #2f81f7; }
        .empty { color: #57606a; font-style: italic; }
    </style>
</head>
<body>
    <h1>{{ $event->name }}</h1>
    <div class="meta">
        {{ $event->venue }} &middot; {{ $event->ticketDateLabel() }}<br>
        Report generated {{ $generatedAt->format('Y-m-d H:i') }}@if ($generatedBy) by {{ $generatedBy }}@endif
    </div>

    <h2>Sales summary</h2>
    <table class="kpis">
        <tr>
            <td><div class="kpi-label">Revenue (paid)</div><div class="kpi-value">{{ $money($summary['revenue']) }}</div></td>
            <td><div class="kpi-label">Tickets sold</div><div class="kpi-value">{{ $summary['ticketsSold'] }} / {{ $summary['capacity'] }}</div></td>
            <td><div class="kpi-label">Capacity sold</div><div class="kpi-value">{{ $summary['capacityUsedPercent'] }}%</div></td>
        </tr>
        <tr>
            <td><div class="kpi-label">Paid orders</div><div class="kpi-value">{{ $summary['paidOrders'] }}</div></td>
            <td><div class="kpi-label">Average order</div><div class="kpi-value">{{ $money($summary['averageOrderValue']) }}</div></td>
            <td><div class="kpi-label">Check-in rate</div><div class="kpi-value">{{ $checkInTotal['rate'] }}%</div></td>
        </tr>
    </table>

    <h2>Ticket types</h2>
    @if (count($ticketTypes) === 0)
        <p class="empty">This event has no ticket types.</p>
    @else
        <table>
            <tr>
                <th>Ticket type</th><th class="num">Price</th><th class="num">Capacity</th><th class="num">Sold</th><th class="num">Available</th><th class="num">Revenue</th><th>Sales window</th>
            </tr>
            @foreach ($ticketTypes as $type)
                <tr>
                    <td>{{ $type['name'] }}</td>
                    <td class="num">{{ $money($type['price']) }}</td>
                    <td class="num">{{ $type['capacity'] }}</td>
                    <td class="num">{{ $type['sold'] }}</td>
                    <td class="num">{{ $type['available'] }}</td>
                    <td class="num">{{ $money($type['revenue']) }}</td>
                    <td>{{ $type['salesStart'] ?? '—' }} → {{ $type['salesEnd'] ?? '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td><td></td>
                <td class="num">{{ $summary['capacity'] }}</td>
                <td class="num">{{ $summary['ticketsSold'] }}</td>
                <td class="num">{{ array_sum(array_column($ticketTypes, 'available')) }}</td>
                <td class="num">{{ $money($summary['revenue']) }}</td>
                <td></td>
            </tr>
        </table>
    @endif

    <h2>Sales over time</h2>
    @if (count($salesByDay) === 0)
        <p class="empty">No paid sales yet.</p>
    @else
        <table>
            <tr>
                <th>Date</th><th class="num">Tickets</th><th style="width: 30%"></th><th class="num">Revenue</th><th class="num">Cumulative tickets</th><th class="num">Cumulative revenue</th>
            </tr>
            @foreach ($salesByDay as $day)
                <tr>
                    <td>{{ $day['date'] }}</td>
                    <td class="num">{{ $day['tickets'] }}</td>
                    <td><div class="bar" style="width: {{ round($day['tickets'] / $maxDayTickets * 100) }}%"></div></td>
                    <td class="num">{{ $money($day['revenue']) }}</td>
                    <td class="num">{{ $day['cumulativeTickets'] }}</td>
                    <td class="num">{{ $money($day['cumulativeRevenue']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Payments</h2>
    <table>
        <tr><th>Order status</th><th class="num">Orders</th><th class="num">Amount</th></tr>
        @foreach ($ordersByStatus as $row)
            <tr>
                <td>{{ ucfirst($row['status']) }}</td>
                <td class="num">{{ $row['orders'] }}</td>
                <td class="num">{{ $money($row['amount']) }}</td>
            </tr>
        @endforeach
    </table>
    <br>
    <table>
        <tr><th>Payment method (paid orders)</th><th class="num">Orders</th><th class="num">Revenue</th></tr>
        @foreach ($revenueByPaymentMethod as $row)
            <tr>
                <td>{{ $row['method'] === 'mpesa' ? 'M-Pesa' : ucfirst($row['method']) }}</td>
                <td class="num">{{ $row['orders'] }}</td>
                <td class="num">{{ $money($row['revenue']) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Check-in &amp; attendance</h2>
    @if ($checkInTotal['issued'] === 0)
        <p class="empty">No tickets issued yet.</p>
    @else
        @foreach (array_filter(['Ticket type' => $checkInByTicketType, 'Event day' => $checkInByDate]) as $heading => $rows)
            <table>
                <tr>
                    <th>{{ $heading }}</th><th class="num">Issued</th><th class="num">Checked in</th><th class="num">No-show</th><th class="num">Voided</th><th class="num">Check-in rate</th>
                </tr>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ $row['issued'] }}</td>
                        <td class="num">{{ $row['checkedIn'] }}</td>
                        <td class="num">{{ $row['noShow'] }}</td>
                        <td class="num">{{ $row['voided'] }}</td>
                        <td class="num">{{ $row['rate'] }}%</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="num">{{ $checkInTotal['issued'] }}</td>
                    <td class="num">{{ $checkInTotal['checkedIn'] }}</td>
                    <td class="num">{{ $checkInTotal['noShow'] }}</td>
                    <td class="num">{{ $checkInTotal['voided'] }}</td>
                    <td class="num">{{ $checkInTotal['rate'] }}%</td>
                </tr>
            </table>
            <br>
        @endforeach
        <p class="meta">Check-in rate counts valid tickets only; voided tickets (refunds, cancellations) are excluded.</p>
    @endif
</body>
</html>
