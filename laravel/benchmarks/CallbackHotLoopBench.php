<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * array_map / usort 自体を PHP の for ループから1万回呼び出すベンチ。
 *
 * CallbackBench は1万要素を1回処理するのに対し、このベンチは小さな配列に対する
 * 関数呼び出しを1万回繰り返し、コールバックを含むホットループ全体への
 * JIT 効果を測る。クロージャは setUp で生成し、ループ内の生成コストを除外する。
 *
 * benchLoopBaseline は同じ「データセット参照 + 加算」を行う基準ループ。
 * 対象処理部分を推定する場合は、同じ JIT 設定の計測値を未丸めのまま差し引く。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(10)]
#[Bench\Iterations(5)]
class CallbackHotLoopBench
{
    private const LOOP_COUNT = 10000;

    private const DATASET_COUNT = 64;

    private const ITEM_COUNT = 16;

    /** @var list<list<int>> */
    private array $datasets;

    /** @var \Closure(int): float */
    private \Closure $mapCallback;

    /** @var \Closure(int, int): int */
    private \Closure $sortCallback;

    /** DCE 防止: 各処理の合計値をプロパティに残す */
    private float $sink = 0.0;

    public function setUp(): void
    {
        $this->datasets = [];
        $seed = 12345;

        for ($datasetIndex = 0; $datasetIndex < self::DATASET_COUNT; $datasetIndex++) {
            $dataset = [];

            for ($itemIndex = 0; $itemIndex < self::ITEM_COUNT; $itemIndex++) {
                $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                $dataset[] = $seed;
            }

            $this->datasets[] = $dataset;
        }

        $this->mapCallback = static function (int $n): float {
            return $n * 1.5 + $n - ($n / 3.0);
        };
        $this->sortCallback = static function (int $a, int $b): int {
            return ($a % 97) <=> ($b % 97);
        };
    }

    /** array_map を1万回呼ぶホットループ全体 */
    public function benchArrayMapHotLoop(): void
    {
        $total = 0.0;
        $datasets = $this->datasets;
        $callback = $this->mapCallback;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $mapped = \array_map($callback, $datasets[$i % self::DATASET_COUNT]);
            $total += $mapped[0];
        }

        $this->sink = $total;
    }

    /** usort を1万回呼ぶホットループ全体 */
    public function benchUsortHotLoop(): void
    {
        $total = 0.0;
        $datasets = $this->datasets;
        $callback = $this->sortCallback;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $data = $datasets[$i % self::DATASET_COUNT];
            \usort($data, $callback);
            $total += $data[0];
        }

        $this->sink = $total;
    }

    /** 対象関数を除いた同形の基準ループ */
    public function benchLoopBaseline(): void
    {
        $total = 0.0;
        $datasets = $this->datasets;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $total += $datasets[$i % self::DATASET_COUNT][0];
        }

        $this->sink = $total;
    }
}
