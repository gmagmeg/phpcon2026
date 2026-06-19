<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 定額クーポン：小計を超えない範囲で固定額を割り引く。 */
final class FixedCoupon implements Coupon
{
    public function __construct(private readonly Money $amount) {}

    public function discountFor(Money $subtotal): Money
    {
        return $this->amount->min($subtotal);
    }
}
