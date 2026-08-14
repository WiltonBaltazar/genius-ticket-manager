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
    {{-- 004-attendee-checkout: admin-configurable payment settings the payment
         step needs client-side (WhatsApp number, bank details) — editable via
         the "Payment Settings" admin page, not .env, so no deploy is needed to
         change them. Js::from() safely escapes this for inline embedding
         (Laravel's recommended pattern for passing server data into JS,
         guards against </script> breakout). --}}
    @php($paymentSettings = \App\Models\PaymentSetting::current())
    <script>
        window.__CHECKOUT_CONFIG__ = {{ Illuminate\Support\Js::from([
            'whatsappNumber' => $paymentSettings->whatsapp_number,
            'bankTransfer' => [
                'accountName' => $paymentSettings->bank_account_name,
                'accountNumber' => $paymentSettings->bank_account_number,
                'bankName' => $paymentSettings->bank_name,
                'branch' => $paymentSettings->bank_branch,
                'instructions' => $paymentSettings->bank_transfer_instructions,
            ],
        ]) }};
    </script>
</body>
</html>
