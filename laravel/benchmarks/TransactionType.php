<?php

declare(strict_types=1);

namespace App\Benchmarks;

enum TransactionType: string
{
    case Withdraw = 'withdraw';
    case Deposit = 'deposit';
    case FeeCharged = 'fee_charged';
}
