<?php

namespace App\Benchmarks;

/**
 * TypedPoint から型宣言を外した等価コード（型なしプロパティ）。
 *
 * コンストラクタ代入が汎用代入パスのままになる。
 *
 * @see ConstructBench
 */
final class LoosePoint
{
    private $x;

    private $y;

    public function __construct($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }
}
