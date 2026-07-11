<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** クーポン割引シナリオの購入者。割引判定に使う属性だけを持つ */
final class CouponCustomer
{
    public function __construct(
        public readonly int $age,
        public readonly int $memberRank,
        public readonly int $birthMonth,
        public readonly int $subtotal,
    ) {
    }
}
