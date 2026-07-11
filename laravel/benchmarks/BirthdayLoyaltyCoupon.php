<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 誕生日割引（継承版）: 購入月が誕生月なら 500 円引き */
final class BirthdayLoyaltyCoupon extends LoyaltyCoupon
{
    public function __construct(
        private readonly int $purchaseMonth,
    ) {
    }

    protected function calculate(CouponCustomer $customer, int $subtotal): int
    {
        return $customer->birthMonth === $this->purchaseMonth ? 500 : 0;
    }
}
