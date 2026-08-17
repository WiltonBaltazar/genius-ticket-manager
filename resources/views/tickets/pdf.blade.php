<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* A fixed 1080×1920 canvas — the ticket is a single portrait pass sized to fill a
           phone screen, not a printable document, so the page box is pixels, not paper. */
        @page {
            size: 1080px 1920px;
            margin: 0;
        }

        @font-face {
            font-family: 'Ticket Display';
            font-weight: 700;
            src: url(data:font/truetype;base64,{{ $fonts['displayBold'] }}) format('truetype');
        }
        @font-face {
            font-family: 'Ticket Sans';
            font-weight: 400;
            src: url(data:font/truetype;base64,{{ $fonts['sansRegular'] }}) format('truetype');
        }
        @font-face {
            font-family: 'Ticket Sans';
            font-weight: 600;
            src: url(data:font/truetype;base64,{{ $fonts['sansSemibold'] }}) format('truetype');
        }
        @font-face {
            font-family: 'Ticket Condensed';
            font-weight: 600;
            src: url(data:font/truetype;base64,{{ $fonts['condensedSemibold'] }}) format('truetype');
        }
        @font-face {
            font-family: 'Ticket Condensed';
            font-weight: 700;
            src: url(data:font/truetype;base64,{{ $fonts['condensedBold'] }}) format('truetype');
        }

        * { box-sizing: border-box; }

        html, body {
            width: 1080px;
            height: 1920px;
            margin: 0;
            padding: 0;
            background: #3C0D5F;
            font-family: 'Ticket Sans', sans-serif;
        }

        .eyebrow {
            font-family: 'Ticket Condensed', sans-serif;
            font-weight: 600;
            font-size: 22px;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #F2A801;
            margin: 0;
        }

        .ticket {
            position: relative;
            width: 960px;
            margin: 60px auto 0;
        }

        /* Header stub: the ticket type is the single largest thing on the page — the
           first thing a scanner (or the attendee, glancing at their phone) needs to read. */
        .header {
            position: relative;
            background: #3C0D5F;
            border-radius: 28px 28px 0 0;
            padding: 56px 56px 48px;
        }

        .logo-chip {
            display: inline-block;
            background: #FFFFFF;
            border-radius: 12px;
            padding: 14px 16px 10px;
            line-height: 0;
        }
        .logo-chip img { height: 56px; width: auto; display: block; }

        .header-eyebrow-row {
            position: absolute;
            top: 68px;
            right: 56px;
            text-align: right;
        }

        .ticket-type-name {
            font-family: 'Ticket Display', serif;
            font-weight: 700;
            font-size: 114px;
            line-height: 1.05;
            color: #FFFFFF;
            margin: 40px 0 0;
        }

        /* Seam: a die-cut tear between the purple stub and the ticket body — the one
           signature borrowed from the rest of the attendee flow's ticket-perforation motif,
           made literal here since this page IS the physical/digital ticket. */
        .seam { position: relative; height: 0; }
        .notch {
            position: absolute;
            top: -26px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #3C0D5F;
        }
        .notch-left { left: -26px; }
        .notch-right { right: -26px; }
        .dash {
            position: absolute;
            top: -1px;
            left: 40px;
            right: 40px;
            border-top: 3px dashed rgba(60, 13, 95, 0.22);
        }

        .body { background: #FFFFFF; padding: 56px 56px 8px; }

        .event-name {
            font-family: 'Ticket Display', serif;
            font-weight: 700;
            font-size: 52px;
            line-height: 1.2;
            color: #3C0D5F;
            margin: 10px 0 0;
        }

        .divider {
            border-top: 1px solid rgba(60, 13, 95, 0.12);
            margin: 32px 0;
        }

        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { width: 50%; padding: 0 0 40px; vertical-align: top; }
        .info-label {
            font-family: 'Ticket Condensed', sans-serif;
            font-weight: 600;
            font-size: 20px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(60, 13, 95, 0.4);
            margin: 0 0 8px;
        }
        .info-value {
            font-family: 'Ticket Sans', sans-serif;
            font-weight: 600;
            font-size: 34px;
            color: #3C0D5F;
            margin: 0;
        }

        .qr-section { text-align: center; margin-top: 12px; }
        .qr-section .eyebrow { text-align: center; }
        .qr-section img {
            width: 572px;
            height: 572px;
            margin: 28px 0 0;
            border: 1px solid rgba(60, 13, 95, 0.1);
            border-radius: 16px;
        }
        .qr-code-text {
            font-family: 'Ticket Condensed', sans-serif;
            font-weight: 600;
            font-size: 22px;
            letter-spacing: 3px;
            color: rgba(60, 13, 95, 0.45);
            margin: 24px 0 0;
            word-break: break-all;
        }

        .footer {
            position: relative;
            background: #3C0D5F;
            border-radius: 0 0 28px 28px;
            padding: 34px 56px;
            text-align: center;
        }
        .footer-wordmark {
            font-family: 'Ticket Condensed', sans-serif;
            font-weight: 600;
            font-size: 20px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
            margin: 0;
        }
        .footer-ref {
            font-family: 'Ticket Condensed', sans-serif;
            font-weight: 600;
            font-size: 20px;
            letter-spacing: 2px;
            color: #F2A801;
            margin: 6px 0 0;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="logo-chip">
                <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Genius Behind the Brands">
            </div>
            <div class="header-eyebrow-row">
                <p class="eyebrow">Bilhete de entrada</p>
            </div>
            <p class="ticket-type-name" style="font-size: {{ $ticketTypeNameFontSize }}px;">{{ $ticketType->name }}</p>
        </div>

        <div class="seam">
            <span class="dash"></span>
            <span class="notch notch-left"></span>
            <span class="notch notch-right"></span>
        </div>

        <div class="body">
            <p class="eyebrow">Evento</p>
            <p class="event-name" style="font-size: {{ $eventNameFontSize }}px;">{{ $event->name }}</p>

            <div class="divider"></div>

            <table class="info-grid">
                <tr>
                    <td>
                        <p class="info-label">Participante</p>
                        <p class="info-value">{{ $attendeeName }}</p>
                    </td>
                    <td>
                        <p class="info-label">Tipo de bilhete</p>
                        <p class="info-value">{{ $ticketType->name }}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p class="info-label">Data</p>
                        <p class="info-value">{{ $event->ticketDateLabel($ticket->event_date) }}</p>
                    </td>
                    <td>
                        @if($event->venue)
                            <p class="info-label">Local</p>
                            <p class="info-value">{{ $event->venue }}</p>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="qr-section">
                <p class="eyebrow">Apresente este código na entrada</p>
                <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="Código QR do bilhete">
                <p class="qr-code-text">{{ $ticket->qr_code }}</p>
            </div>
        </div>

        <div class="footer">
            <p class="footer-wordmark">Genius Behind the Brands</p>
            <p class="footer-ref">Pedido #{{ strtoupper(substr($order->id, 0, 8)) }}</p>
        </div>
    </div>
</body>
</html>
