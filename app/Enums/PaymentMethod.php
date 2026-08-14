<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Mpesa = 'mpesa';
    case Offline = 'offline';
}
