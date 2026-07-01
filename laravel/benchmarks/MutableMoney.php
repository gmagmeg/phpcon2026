<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class MutableMoney
{
    public function __construct(private int $amount) {}

    public function addAmount(int $amount): void
    {
        $this->amount += $amount;
    }

    public function subtractAmount(int $amount): void
    {
        $this->amount -= $amount;
    }

    public function amount(): int
    {
        return $this->amount;
    }
}
