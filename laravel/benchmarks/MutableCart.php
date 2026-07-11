<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * ミュータブルなカート。内部配列を破壊的更新するため、
 * add / removeLast で新しいオブジェクトは生まれない（new はカート生成の 1 回だけ）。
 */
final class MutableCart
{
    /** @var list<ScenarioItem> */
    private array $items = [];

    public function add(ScenarioItem $item): void
    {
        $this->items[] = $item;
    }

    /** 最後に入れた商品を 1 点取り出す */
    public function removeLast(): void
    {
        array_pop($this->items);
    }

    public function totalAmount(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->price;
        }

        return $total;
    }
}
