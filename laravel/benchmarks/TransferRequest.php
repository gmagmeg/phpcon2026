<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class TransferRequest
{
    public function __construct(
        public AccountId $payerId,
        public ParticipantName $payerName,
        public AccountId $payeeId,
        public ParticipantName $payeeName,
        public ParticipantName $requestedBy,
        public Money $amount,
        public OccurredAt $requestedAt,
        public IdempotencyKey $idempotencyKey,
    ) {}
}
