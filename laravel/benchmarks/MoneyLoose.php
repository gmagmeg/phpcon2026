<?php

namespace App\Benchmarks;

/**
 * Money から型宣言を外しただけの等価コード（型なし）。
 *
 * 型が不明なため、JIT ON でもガード・汎用パスが残り、改善は鈍い。
 *
 * @see MoneyBench 水準 C
 */
final class MoneyLoose
{
    private $amount;

    public function __construct($amount)
    {
        $this->amount = $amount;
    }

    public function add($o)
    {
        return new self($this->amount + $o->amount);
    }

    public function multiply($f)
    {
        return new self($this->amount * $f);
    }

    public function amount()
    {
        return $this->amount;
    }
}
