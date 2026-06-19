<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * クーポン割引のポリモーフィズム境界。
 *
 * 呼び出し側は $coupon->discountFor($subtotal) を書くだけで、
 * 定額 / 率 / 条件付き の分岐は各実装に閉じる（配列版の switch と対比）。
 *
 * 同じ実装だけが来る呼び出し点（単型）なら JIT は dispatch を
 * インライン化できる。3 種が混ざる（多型）と virtual call が残る。
 */
interface Coupon
{
    public function discountFor(Money $subtotal): Money;
}
