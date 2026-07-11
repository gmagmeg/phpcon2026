<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 継承によるポリモーフィズム版のクーポン基底クラス。
 *
 * 共通処理（割引は小計を超えない）を基底に持ち、個別ルールは
 * calculate() のオーバーライドで表すテンプレートメソッド構成。
 * 1 回の割引 = discountFor → calculate → clamp の 3 フレームを積む。
 * interface 版（DiscountCoupon: 各実装がフラット）との対比用。
 */
abstract class LoyaltyCoupon
{
    /** 共通の入口: 個別計算 → 共通の上限処理 */
    public function discountFor(CouponCustomer $customer, int $subtotal): int
    {
        $discount = $this->calculate($customer, $subtotal);

        return $this->clamp($discount, $subtotal);
    }

    abstract protected function calculate(CouponCustomer $customer, int $subtotal): int;

    /** 割引は小計を超えない（共通処理は基底に置く） */
    protected function clamp(int $discount, int $subtotal): int
    {
        return $discount > $subtotal ? $subtotal : $discount;
    }
}
