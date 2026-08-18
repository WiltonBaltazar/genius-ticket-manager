<!DOCTYPE html>
<html lang="pt" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<meta name="x-apple-disable-message-reformatting">
<title>{{ $subject }}</title>
{{-- Bunny Fonts (same brand faces as the web app, privacy-respecting) — progressive
     enhancement only. Clients that ignore @import (Outlook desktop, most webmail)
     fall back to the Georgia/Arial stacks declared inline on every element below. --}}
<style>
    @import url('https://fonts.bunny.net/css?family=playfair-display:700|barlow:400,600|barlow-condensed:600,700');
    body, table, td { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #FFFFFF; }

    @media (max-width: 600px) {
        .gtb-card { width: 100% !important; }
        .gtb-px { padding-left: 20px !important; padding-right: 20px !important; }
        .gtb-headline { font-size: 24px !important; }
    }
</style>
</head>
<body style="margin:0; padding:0; background-color:#FFFFFF;">
    {{-- Preheader: hidden inbox-preview text, not shown once the email is open. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px; color:#FFFFFF;">
        {{ $preheader }}
        &#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;
    </div>

    {{-- Global header: plain white bar, logo only — matches the attendee site's own
         <header className="border-b border-deep-purple/10 px-6 py-4"> exactly. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFF;">
        <tr>
            <td align="center" style="border-bottom: 1px solid rgba(60,13,95,0.1); padding: 18px 16px;">
                <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" height="32" style="display:block; height:32px; width:auto;">
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFF;">
        <tr>
            <td align="center" style="padding: 40px 16px;">

                {{-- The order-status card, echoing resources/js/components/checkout/OrderStatus.tsx:
                     purple stub header, torn seam, white body — the site's own signature
                     ticket-stub motif, not an invented one. Status-dependent bits (badge,
                     headline, intro, CTA) are passed in by the calling notification so the
                     pending and paid emails share one template instead of two near-copies. --}}
                <table role="presentation" class="gtb-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    <tr>
                        <td bgcolor="#3C0D5F" class="gtb-px" style="background-color:#3C0D5F; border:1px solid rgba(60,13,95,0.12); border-bottom:none; border-radius:16px 16px 0 0; padding: 24px 28px 20px;">
                            <p style="margin:0; font-family:'Barlow Condensed', Arial, sans-serif; font-weight:600; font-size:12px; line-height:16px; letter-spacing:3px; text-transform:uppercase; color:#F2A801;">
                                Pedido #{{ $orderRef }}
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top: 12px;">
                                <tr>
                                    @if ($status === 'paid')
                                        <td bgcolor="#F2A801" style="background-color:#F2A801; border-radius:999px; padding: 5px 14px;">
                                            <span style="font-family:'Barlow Condensed', Arial, sans-serif; font-weight:600; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#3C0D5F;">Pago</span>
                                        </td>
                                    @elseif ($status === 'refunded')
                                        <td style="background-color:rgba(255,255,255,0.15); border-radius:999px; padding: 5px 14px;">
                                            <span style="font-family:'Barlow Condensed', Arial, sans-serif; font-weight:600; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:rgba(255,255,255,0.85);">Reembolsado</span>
                                        </td>
                                    @else
                                        <td style="border:1px solid rgba(255,255,255,0.35); border-radius:999px; padding: 5px 14px;">
                                            <span style="font-family:'Barlow Condensed', Arial, sans-serif; font-weight:600; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:rgba(255,255,255,0.9);">Aguarda pagamento</span>
                                        </td>
                                    @endif
                                </tr>
                            </table>

                            <p class="gtb-headline" style="margin: 20px 0 0; font-family:'Playfair Display', Georgia, 'Times New Roman', serif; font-weight:700; font-size:28px; line-height:1.25; color:#FFFFFF;">
                                {{ $headline }}
                            </p>
                        </td>
                    </tr>

                    {{-- Torn seam: dashed line + two punched notches, same motif as the
                         website card's seam and the ticket PDF's own die-cut tear. --}}
                    <tr>
                        <td style="border-left:1px solid rgba(60,13,95,0.12); border-right:1px solid rgba(60,13,95,0.12); padding:0; line-height:0; font-size:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#3C0D5F" style="background-color:#3C0D5F;">
                                <tr>
                                    <td width="24" align="center" valign="middle" style="height:22px;">
                                        <div style="width:12px; height:12px; margin:0 auto; border-radius:50%; background-color:#FFFFFF;"></div>
                                    </td>
                                    <td valign="middle" style="height:22px;">
                                        <div style="border-top:2px dashed rgba(255,255,255,0.3); height:0; margin: 0 8px;">&nbsp;</div>
                                    </td>
                                    <td width="24" align="center" valign="middle" style="height:22px;">
                                        <div style="width:12px; height:12px; margin:0 auto; border-radius:50%; background-color:#FFFFFF;"></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td bgcolor="#FFFFFF" class="gtb-px" style="background-color:#FFFFFF; border:1px solid rgba(60,13,95,0.12); border-top:none; border-radius:0 0 16px 16px; padding: 24px 28px 28px;">
                            <p style="margin:0 0 4px; font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#3C0D5F;">
                                Olá {{ $attendeeName }},
                            </p>
                            <p style="margin:0 0 20px; font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:rgba(60,13,95,0.7);">
                                {{ $introLine }}
                            </p>

                            {{-- Nested bordered list, matching the site's own
                                 <ul class="divide-y divide-deep-purple/10 rounded-md border border-deep-purple/10"> --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid rgba(60,13,95,0.1); border-radius:6px;">
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding: 12px 16px; {{ ! $loop->first ? 'border-top:1px solid rgba(60,13,95,0.1);' : '' }} font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#3C0D5F;">
                                            {{ $item->quantity }} &times; {{ $item->ticketType->name }}
                                            @if ($item->event_date)
                                                <span style="color:rgba(60,13,95,0.5);">({{ $item->event_date->locale('pt')->translatedFormat('d \d\e F') }})</span>
                                            @endif
                                        </td>
                                        <td align="right" style="padding: 12px 16px; {{ ! $loop->first ? 'border-top:1px solid rgba(60,13,95,0.1);' : '' }} font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#3C0D5F; white-space:nowrap;">
                                            MZN {{ number_format((float) $item->subtotal, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding: 12px 16px; border-top:1px solid rgba(60,13,95,0.1); font-family:'Barlow', Arial, Helvetica, sans-serif; font-weight:600; font-size:14px; line-height:20px; color:#3C0D5F;">
                                        {{ $totalLabel ?? 'Total' }}
                                    </td>
                                    <td align="right" style="padding: 12px 16px; border-top:1px solid rgba(60,13,95,0.1); font-family:'Barlow', Arial, Helvetica, sans-serif; font-weight:600; font-size:14px; line-height:20px; color:#3C0D5F; white-space:nowrap;">
                                        MZN {{ number_format((float) $totalAmount, 2) }}
                                    </td>
                                </tr>
                            </table>

                            @if ($status === 'pending' && $expiresAt)
                                <p style="margin: 12px 0 0; font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(60,13,95,0.5);">
                                    Este pedido expira às {{ $expiresAt->locale('pt')->translatedFormat('H:i \d\e d \d\e F') }} — depois disso os bilhetes voltam a ficar disponíveis para outros compradores.
                                </p>
                            @endif

                            {{-- Bulletproof button: table+td background, not an <a> with padding, so Outlook
                                 renders it solid. Solid deep-purple / white text — the site's own primary-button
                                 treatment (CheckoutDetailsForm's submit button), not an invented gold button.
                                 Optional: a refunded order has nothing left to do, so there's no CTA. --}}
                            @if ($ctaLabel)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 28px auto 0;">
                                    <tr>
                                        <td align="center" bgcolor="#3C0D5F" style="background-color:#3C0D5F; border-radius:999px;">
                                            <a href="{{ $orderUrl }}" target="_blank" style="display:inline-block; padding:13px 30px; font-family:'Barlow Condensed', Arial, sans-serif; font-weight:600; font-size:14px; letter-spacing:1px; text-transform:uppercase; color:#FFFFFF; text-decoration:none;">
                                                {{ $ctaLabel }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            @if ($helperLine)
                                <p style="margin: {{ $ctaLabel ? '14px' : '28px' }} 0 0; font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(60,13,95,0.5); text-align:center;">
                                    {{ $helperLine }}
                                </p>
                            @endif
                        </td>
                    </tr>

                </table>

                <p style="margin: 24px 0 0; font-family:'Barlow', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(60,13,95,0.4); text-align:center;">
                    Recebeu este e-mail porque efetuou uma compra em {{ config('app.name') }}.
                </p>

            </td>
        </tr>
    </table>
</body>
</html>
