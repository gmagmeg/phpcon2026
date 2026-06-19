<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 送料ポリシー：無料ライン判定 ＋「配送地域 × 重量」テーブル引き。
 *
 * 割引後小計が無料ライン以上なら 0 円。そうでなければ ShippingRateTable を
 * 地域で引き、重量段階に当てはめて送料を決める（配列版と同じルール）。
 */
final class ShippingPolicy
{
    public function __construct(
        private readonly Money $freeThreshold,
        private readonly ShippingRateTable $table,
        private readonly Money $fallbackFee,
    ) {}

    public function feeFor(Money $discountedSubtotal, Region $region, int $weight): Money
    {
        if ($discountedSubtotal->greaterThanOrEqual($this->freeThreshold)) {
            return Money::zero();
        }

        foreach ($this->table->ratesFor($region) as $rate) {
            if ($weight <= $rate->maxWeight) {
                return $rate->fee;
            }
        }

        return $this->fallbackFee;
    }
}
