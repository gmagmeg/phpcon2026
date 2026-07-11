<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * イミュータブルなカート。add / removeLast は自身を変更せず
 * 新しいカートを返すため、操作のたびに
 *   new ItemInCart（add 時）＋ new ImmutableCart
 * のアロケーションが発生する。
 */
final class ImmutableCart
{
    /** @param list<ItemInCart> $items */
    public function __construct(
        private readonly array $items = [],
    ) {
    }

    public function add(ScenarioItem $item): self
    {
        $items = $this->items;
        $items[] = new ItemInCart($item);

        return new self($items);
    }

    /** 最後に入れた商品を 1 点取り出したカートを返す */
    public function removeLast(): self
    {
        $items = $this->items;
        array_pop($items);

        return new self($items);
    }

    public function totalAmount(): int
    {
        $total = 0;
        foreach ($this->items as $itemInCart) {
            $total += $itemInCart->price();
        }

        return $total;
    }
}
