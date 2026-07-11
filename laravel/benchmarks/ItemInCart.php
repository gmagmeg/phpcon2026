<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * イミュータブルなカートが商品を保持するときのラッパー。
 * 「カートに入った商品」という文脈を型で表す代わりに、
 * add のたびにこのオブジェクトの生成コストが 1 つ増える。
 */
final class ItemInCart
{
    public function __construct(
        public readonly ScenarioItem $item,
    ) {
    }

    public function price(): int
    {
        return $this->item->price;
    }
}
