<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class MutableMoney
{
    public function __construct(private int $amount) {}

    /** 値を入れ替える（new せず再利用するための破壊的セッター）。 */
    public function set(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /** 破壊的加算。new を返さず自身を更新して返す（@see MoneyBench 水準 B）。 */
    public function add(MutableMoney $o): self
    {
        $this->amount += $o->amount;

        return $this;
    }

    /** 破壊的乗算。new を返さず自身を更新して返す。 */
    public function multiply(int $f): self
    {
        $this->amount *= $f;

        return $this;
    }

    public function addAmount(int $amount): void
    {
        $this->amount += $amount;
    }

    public function subtractAmount(int $amount): void
    {
        $this->amount -= $amount;
    }

    public function amount(): int
    {
        return $this->amount;
    }
}
