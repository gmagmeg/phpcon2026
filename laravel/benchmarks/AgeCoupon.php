<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 年齢割引（interface 版）: 60 歳以上なら小計の 10% 引き */
final class AgeCoupon implements DiscountCoupon
{
    public function discountFor(CouponCustomer $customer, int $subtotal): int
    {
        $discount = $customer->age >= 60 ? intdiv($subtotal, 10) : 0;

        return $discount > $subtotal ? $subtotal : $discount;
    }
}
