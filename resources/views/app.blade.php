<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Genius Behind the Brands') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    <div id="root"></div>
    {{-- 004-attendee-checkout: static payment config the payment step needs
         client-side (WhatsApp number, bank details) — Js::from() safely
         escapes this for inline embedding (Laravel's recommended pattern for
         passing server data into JS, guards against </script> breakout). --}}
    <script>
        window.__CHECKOUT_CONFIG__ = {{ Illuminate\Support\Js::from([
            'whatsappNumber' => config('services.whatsapp.number'),
            'bankTransfer' => [
                'accountName' => config('services.bank_transfer.account_name'),
                'accountNumber' => config('services.bank_transfer.account_number'),
                'bankName' => config('services.bank_transfer.bank_name'),
                'branch' => config('services.bank_transfer.branch'),
            ],
        ]) }};
    </script>
</body>
</html>
