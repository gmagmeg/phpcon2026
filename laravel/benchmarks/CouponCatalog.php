<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * このショップが用意するクーポン集合（型付き版用）。
 *
 * 手続き（配列）版は CartCheckoutArrayBench の const COUPONS に同じ内容・
 * 同じ並びを持つので、両版の結果は一致する。
 */
final class CouponCatalog
{
    /**
     * @return list<Coupon>
     */
    public function typed(): array
    {
        return [
            new FixedCoupon(new Money(500)),
            new RateCoupon(10),
            new ThresholdCoupon(new Money(5000), new Money(1200)),
        ];
    }
}
