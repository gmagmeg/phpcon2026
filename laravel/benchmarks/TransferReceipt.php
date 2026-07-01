<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class TransferReceipt
{
    /**
     * @param list<TransactionEvent> $events
     */
    public function __construct(
        public TransferRequest $request,
        public TransferStatus $status,
        public Money $fee,
        public array $events,
    ) {}
}
