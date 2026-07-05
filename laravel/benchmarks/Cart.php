<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * カートの値オブジェクト：明細の集合 ＋ 配送地域。
 *
 * 小計・総重量の算出を自身の責務として閉じる。呼び出し側は
 * $cart->subtotal() / $cart->totalWeight() を読むだけでよい。
 */
final class Cart
{
    /**
     * @param list<CartLine> $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly Region $region,
    ) {}

    /** 小計 ＝ 明細小計の合計 */
    public function subtotal(): Money
    {
        $subtotal = Money::zero();
        foreach ($this->lines as $line) {
            $subtotal = $subtotal->add($line->subtotal());
        }

        return $subtotal;
    }

    /** 総重量 ＝ 明細重量の合計 */
    public function totalWeight(): int
    {
        $weight = 0;
        foreach ($this->lines as $line) {
            $weight += $line->weight();
        }

        return $weight;
    }
}
