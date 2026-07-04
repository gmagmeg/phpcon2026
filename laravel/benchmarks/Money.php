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

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(Money $o): self
    {
        return new self($this->amount + $o->amount);
    }

    public function subtract(Money $o): self
    {
        return new self($this->amount - $o->amount);
    }

    public function multiply(int $f): self
    {
        return new self($this->amount * $f);
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        return $this->amount >= $other->amount;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    /**
     * 値オブジェクトの等価判定。中身は int 同士の === 比較なので、
     * JIT がインライン化すれば getter 直比較と同じ命令に潰れる。
     */
    public function equal(Money $other): bool
    {
        return $this->amount === $other->amount;
    }
}
