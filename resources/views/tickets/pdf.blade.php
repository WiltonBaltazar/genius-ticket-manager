<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #3C0D5F; padding: 40px; }
        .eyebrow { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #F2A801; }
        h1 { font-size: 28px; margin: 8px 0 4px; }
        .meta { font-size: 14px; margin: 4px 0; }
        .qr { margin-top: 32px; text-align: center; }
        .code { margin-top: 12px; font-size: 11px; letter-spacing: 1px; color: #3C0D5F99; }
        .logo { width: 160px; margin-bottom: 24px; }
    </style>
</head>
<body>
    <img class="logo" src="data:image/png;base64,{{ $logoBase64 }}" alt="Genius Behind the Brands">
    <p class="eyebrow">{{ $event->name }}</p>
    <h1>{{ $ticketType->name }}</h1>
    <p class="meta">Attendee: {{ $attendeeName }}</p>
    <p class="meta">Venue: {{ $event->venue }}</p>
    <p class="meta">Date: {{ $event->start_date->format('F j, Y g:i A') }}</p>

    <div class="qr">
        <img src="data:image/png;base64,{{ $qrCodeBase64 }}" width="180" height="180" alt="Ticket QR code">
        <p class="code">{{ $ticket->qr_code }}</p>
    </div>
</body>
</html>
