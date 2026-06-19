<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 型付きの値オブジェクト（PHP 8.1+ / readonly + 型宣言）。
 *
 * 型が静的に分かるため、JIT はメソッドをインライン化し、
 * プロパティを直接スロットアクセスし、int 演算として特殊化できる。
 *
 * @see MoneyBench 水準 B
 */
final class Money
{
    public function __construct(private readonly int $amount) {}

    public function add(Money $o): self
    {
        return new self($this->amount + $o->amount);
    }

    public function multiply(int $f): self
    {
        return new self($this->amount * $f);
    }

    public function amount(): int
    {
        return $this->amount;
    }
}
