<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class AccountRegistry
{
    /** @var array<string, Account> */
    private array $accounts = [];

    /**
     * @param list<Account> $accounts
     */
    public function __construct(array $accounts)
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->id()->key()] = $account;
        }
    }

    public function require(AccountId $id): Account
    {
        return $this->accounts[$id->key()];
    }

    public function totalBalance(): int
    {
        $total = 0;
        foreach ($this->accounts as $account) {
            $total += $account->balance();
        }

        return $total;
    }

    public function totalHistoryCount(): int
    {
        $total = 0;
        foreach ($this->accounts as $account) {
            $total += $account->historyCount();
        }

        return $total;
    }
}
