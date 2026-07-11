<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * インターフェースによるポリモーフィズム版のクーポン境界。
 *
 * 各実装は計算も上限処理（割引は小計を超えない）も自分の中で
 * フラットに完結させる。1 回の割引 = メソッド呼び出し 1 フレーム。
 * 継承版（LoyaltyCoupon: 基底のテンプレートメソッド経由）との対比用。
 */
interface DiscountCoupon
{
    public function discountFor(CouponCustomer $customer, int $subtotal): int;
}
