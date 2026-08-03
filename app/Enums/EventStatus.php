<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case SoldOut = 'sold_out';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
