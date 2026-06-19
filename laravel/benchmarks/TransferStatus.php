<?php

declare(strict_types=1);

namespace App\Benchmarks;

enum TransferStatus: string
{
    case Completed = 'completed';
    case DuplicateIgnored = 'duplicate_ignored';
    case Rejected = 'rejected';
}
