<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * Laravel Collection の抽象化コスト vs 素の配列。
 * Collection は「ユーザーランドPHP」なので JIT ON/OFF で差が出うる。
 *
 * 実行:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CollectionBench.php --report=default
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(2000)]
#[Bench\Iterations(5)]
class CollectionBench extends LaravelBench
{
    private array $data;

    public function setUp(): void
    {
        parent::setUp();
        $this->data = range(1, 1000);
    }

    public function benchCollectionPipeline(): void
    {
        collect($this->data)
            ->map(fn ($n) => $n * 2)
            ->filter(fn ($n) => $n % 3 === 0)
            ->sum();
    }

    public function benchRawArray(): void
    {
        $sum = 0;
        foreach ($this->data as $n) {
            $v = $n * 2;
            if ($v % 3 === 0) {
                $sum += $v;
            }
        }
    }

    public function benchArrayFunctions(): void
    {
        $mapped = array_map(fn ($n) => $n * 2, $this->data);
        $filtered = array_filter($mapped, fn ($n) => $n % 3 === 0);
        array_sum($filtered);
    }
}
