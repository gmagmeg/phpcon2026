<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** カートシナリオ用の商品。価格だけを持つ */
final class ScenarioItem
{
    public function __construct(
        public readonly int $price,
    ) {
    }
}
