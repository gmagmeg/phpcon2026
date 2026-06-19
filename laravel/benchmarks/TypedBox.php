<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 型付き readonly プロパティを 1 つ持つだけの箱。
 *
 * アロケーションを排した純粋比較用：生成済みインスタンスのプロパティを
 * メソッド経由でホットループから読み続ける。tracing JIT は value() を
 * ループのトレースにインライン化し、int として特殊化できる。
 *
 * @see HotLoopBench
 */
final class TypedBox
{
    public function __construct(private readonly int $value) {}

    public function value(): int
    {
        return $this->value;
    }
}
