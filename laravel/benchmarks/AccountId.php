<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class AccountId
{
    public function __construct(public string $value) {}

    public function key(): string
    {
        return $this->value;
    }
}
