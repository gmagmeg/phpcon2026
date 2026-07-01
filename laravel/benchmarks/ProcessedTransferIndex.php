<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class ProcessedTransferIndex
{
    /** @var array<string, true> */
    private array $keys = [];

    public function has(IdempotencyKey $key): bool
    {
        return isset($this->keys[$key->key()]);
    }

    public function remember(IdempotencyKey $key): void
    {
        $this->keys[$key->key()] = true;
    }

    public function count(): int
    {
        return count($this->keys);
    }
}
