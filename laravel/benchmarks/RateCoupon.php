<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 率クーポン：小計の N パーセントを割り引く（端数切り捨て）。 */
final class RateCoupon implements Coupon
{
    public function __construct(private readonly int $percent) {}

    public function discountFor(Money $subtotal): Money
    {
        return $subtotal->percent($this->percent);
    }
}
