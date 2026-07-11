<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 会員割引（interface 版）: 会員ランク × 300 円引き */
final class MemberCoupon implements DiscountCoupon
{
    public function discountFor(CouponCustomer $customer, int $subtotal): int
    {
        $discount = $customer->memberRank * 300;

        return $discount > $subtotal ? $subtotal : $discount;
    }
}
