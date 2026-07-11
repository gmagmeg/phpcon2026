<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 会員割引（継承版）: 会員ランク × 300 円引き */
final class MemberLoyaltyCoupon extends LoyaltyCoupon
{
    protected function calculate(CouponCustomer $customer, int $subtotal): int
    {
        return $customer->memberRank * 300;
    }
}
