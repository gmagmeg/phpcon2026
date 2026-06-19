<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * カート明細の値オブジェクト（単価 × 数量 ＋ 重量）。
 *
 * 型が静的に分かるため、subtotal() / weight() の呼び出しは
 * JIT がインライン化し、int 演算として特殊化できる。
 */
final class CartLine
{
    public function __construct(
        private readonly Money $unitPrice,
        private readonly int $qty,
        private readonly int $unitWeight,
    ) {}

    /** 明細小計 ＝ 単価 × 数量 */
    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->qty);
    }

    /** 明細重量 ＝ 単品重量 × 数量 */
    public function weight(): int
    {
        return $this->unitWeight * $this->qty;
    }
}
