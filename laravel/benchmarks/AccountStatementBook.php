<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class AccountStatementBook
{
    /** @var array<string, AccountStatement> */
    private array $statements = [];

    public function applyReceipt(TransferReceipt $receipt): void
    {
        foreach ($receipt->events as $event) {
            $this->apply($event);
        }
    }

    public function apply(TransactionEvent $event): void
    {
        $key = $event->accountId->key();

        if (! isset($this->statements[$key])) {
            $this->statements[$key] = new AccountStatement($event->accountId);
        }

        $this->statements[$key]->apply($event);
    }

    public function checksum(): int
    {
        $checksum = 0;
        foreach ($this->statements as $statement) {
            $checksum += $statement->checksum();
        }

        return $checksum;
    }
}
