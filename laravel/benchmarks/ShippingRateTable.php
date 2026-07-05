<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 「配送地域 × 重量」の送料テーブル。
 *
 * 各地域について重量段階（昇順）とその送料を保持する。かつて
 * ベンチの const 配列だったデータを、この 1 クラスに閉じ込める。
 */
final class ShippingRateTable
{
    /** @var array<string, list<ShippingRate>> */
    private array $ratesByRegion;

    public function __construct()
    {
        $this->ratesByRegion = [
            Region::North->value => [
                new ShippingRate(500, new Money(800)),
                new ShippingRate(2000, new Money(1100)),
                new ShippingRate(5000, new Money(1500)),
            ],
            Region::Central->value => [
                new ShippingRate(500, new Money(600)),
                new ShippingRate(2000, new Money(900)),
                new ShippingRate(5000, new Money(1300)),
            ],
            Region::South->value => [
                new ShippingRate(500, new Money(700)),
                new ShippingRate(2000, new Money(1000)),
                new ShippingRate(5000, new Money(1400)),
            ],
        ];
    }

    /** @return list<ShippingRate> 昇順の重量段階 */
    public function ratesFor(Region $region): array
    {
        return $this->ratesByRegion[$region->value] ?? [];
    }
}
