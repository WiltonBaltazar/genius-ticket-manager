<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // WhatsApp checkout (004-attendee-checkout): a wa.me deep link to the
    // organizer's number, not an API integration — see research.md §6.
    'whatsapp' => [
        'number' => env('WHATSAPP_ORGANIZER_NUMBER'),
    ],

    // Bank-transfer payment method (004-attendee-checkout): displayed as
    // static instructions on the checkout payment step.
    'bank_transfer' => [
        'account_name' => env('BANK_TRANSFER_ACCOUNT_NAME'),
        'account_number' => env('BANK_TRANSFER_ACCOUNT_NUMBER'),
        'bank_name' => env('BANK_TRANSFER_BANK_NAME'),
        'branch' => env('BANK_TRANSFER_BRANCH'),
    ],

];
