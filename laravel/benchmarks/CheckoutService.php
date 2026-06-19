<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * カート確定処理のオーケストレーション：小計 → 割引 → 送料 → 確定。
 *
 * 各ステップの中身（クーポン割引・送料計算）は Coupon / ShippingPolicy に
 * 委譲する。配列版が 1 つの手続きに全部を書くのに対し、型付き版は
 * この 1 メソッドが VO 群を組み合わせるだけになる。
 */
final class CheckoutService
{
    public function __construct(private readonly ShippingPolicy $shippingPolicy) {}

    public function checkout(Cart $cart, Coupon $coupon): CheckoutReceipt
    {
        $subtotal = $cart->subtotal();
        $discount = $coupon->discountFor($subtotal);
        $discounted = $subtotal->subtract($discount);
        $shipping = $this->shippingPolicy->feeFor($discounted, $cart->region, $cart->totalWeight());

        return new CheckoutReceipt(
            $subtotal,
            $discount,
            $shipping,
            $discounted->add($shipping),
        );
    }
}
