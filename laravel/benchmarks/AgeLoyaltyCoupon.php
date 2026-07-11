<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 年齢割引（継承版）: 60 歳以上なら小計の 10% 引き */
final class AgeLoyaltyCoupon extends LoyaltyCoupon
{
    protected function calculate(CouponCustomer $customer, int $subtotal): int
    {
        return $customer->age >= 60 ? intdiv($subtotal, 10) : 0;
    }
}
