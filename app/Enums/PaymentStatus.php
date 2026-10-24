<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
