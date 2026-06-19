<?php

namespace App\Benchmarks;

/**
 * TypedBox から型宣言を外した等価コード（型なしプロパティ）。
 *
 * 型が不明なため、tracing JIT でもガード・汎用パスが残る。
 *
 * @see HotLoopBench
 */
final class LooseBox
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function value()
    {
        return $this->value;
    }
}
