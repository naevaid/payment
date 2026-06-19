<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Settlement = 'settlement';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
