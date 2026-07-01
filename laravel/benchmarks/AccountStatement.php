<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class AccountStatement
{
    private int $debitTotal = 0;
    private int $creditTotal = 0;
    private int $feeTotal = 0;
    private int $eventCount = 0;
    private int $endingBalance = 0;

    public function __construct(private readonly AccountId $accountId) {}

    public function apply(TransactionEvent $event): void
    {
        $amount = $event->amount->amount();

        switch ($event->operation) {
            case TransactionType::Withdraw:
                $this->debitTotal += $amount;
                break;

            case TransactionType::Deposit:
                $this->creditTotal += $amount;
                break;

            case TransactionType::FeeCharged:
                $this->debitTotal += $amount;
                $this->feeTotal += $amount;
                break;
        }

        $this->endingBalance = $event->balance;
        $this->eventCount++;
    }

    public function checksum(): int
    {
        return $this->debitTotal
            + $this->creditTotal
            + $this->feeTotal
            + $this->eventCount
            + $this->endingBalance
            + strlen($this->accountId->key());
    }
}
