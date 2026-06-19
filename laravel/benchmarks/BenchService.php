<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** コンテナ解決ベンチ用のサービス（BenchDependency に依存）。 */
final class BenchService
{
    public function __construct(public BenchDependency $dep) {}
}
