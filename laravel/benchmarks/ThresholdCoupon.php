<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 条件付きクーポン：小計が閾値以上のときだけ固定額を割り引く。 */
final class ThresholdCoupon implements Coupon
{
    public function __construct(
        private readonly Money $threshold,
        private readonly Money $amount,
    ) {}

    public function discountFor(Money $subtotal): Money
    {
        return $subtotal->greaterThanOrEqual($this->threshold)
            ? $this->amount
            : Money::zero();
    }
}
