<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 型付き readonly プロパティの値オブジェクト。
 *
 * コンストラクタ代入時に JIT が型検証を省き、int は refcount も不要なので
 * 素のストアになる。
 *
 * @see ConstructBench
 */
final class TypedPoint
{
    public function __construct(
        private readonly int $x,
        private readonly int $y,
    ) {}
}
