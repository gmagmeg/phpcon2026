<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 誕生日割引（interface 版）: 購入月が誕生月なら 500 円引き */
final class BirthdayCoupon implements DiscountCoupon
{
    public function __construct(
        private readonly int $purchaseMonth,
    ) {
    }

    public function discountFor(CouponCustomer $customer, int $subtotal): int
    {
        $discount = $customer->birthMonth === $this->purchaseMonth ? 500 : 0;

        return $discount > $subtotal ? $subtotal : $discount;
    }
}
