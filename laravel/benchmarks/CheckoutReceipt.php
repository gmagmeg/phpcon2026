<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 確定結果の値オブジェクト：小計・割引・送料・請求額の内訳。 */
final class CheckoutReceipt
{
    public function __construct(
        public readonly Money $subtotal,
        public readonly Money $discount,
        public readonly Money $shipping,
        public readonly Money $total,
    ) {}
}
