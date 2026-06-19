<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 送料テーブルの 1 段：重量上限とその送料。 */
final class ShippingRate
{
    public function __construct(
        public readonly int $maxWeight,
        public readonly Money $fee,
    ) {}
}
