<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 入出金イベント（イミュータブルな値オブジェクト）。
 */
final readonly class TransactionEvent
{
    public function __construct(
        public AccountId $accountId,
        public readonly OccurredAt $occurredAt,
        public readonly IdempotencyKey $reference,
        public readonly TransactionType $operation,
        public readonly Money $amount,
        public readonly ParticipantName $name,
        public readonly int $balance,
    ) {}
}
