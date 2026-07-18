<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * Collection / 素の配列 / 配列関数の処理パイプラインをホットループで比較する。
 *
 * CollectionBench がパイプライン1回単位のコストを測るのに対し、このベンチでは
 * PHP コード内で異なるデータセットを連続処理し、外側のループを含む実用的な
 * 繰り返し処理全体への JIT 効果を測る。
 *
 * benchLoopBaseline は同じ「データセット参照 + 加算」を行う基準ループ。
 * 対象処理部分を推定する場合は、同じ JIT 設定の計測値を未丸めのまま差し引く。
 * ホットループ全体を比較する場合は、各 bench* の計測値をそのまま使う。
 *
 *   # JIT OFF:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CollectionHotLoopBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":0}'
 *   # function JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CollectionHotLoopBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":"64M","opcache.jit":"function"}'
 *   # tracing JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CollectionHotLoopBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":"64M","opcache.jit":"tracing"}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(10)]
#[Bench\Iterations(5)]
class CollectionHotLoopBench extends LaravelBench
{
    private const LOOP_COUNT = 100;

    private const ITEM_COUNT = 1000;

    /** @var list<list<int>> ループごとに異なる入力を使う */
    private array $datasets;

    /** DCE 防止: 各パイプラインの合計値をプロパティに残す */
    private int $sink = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->datasets = [];

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $start = $i + 1;
            $this->datasets[] = range($start, $start + self::ITEM_COUNT - 1);
        }
    }

    /** Collection パイプラインを含むホットループ全体 */
    public function benchCollectionPipelineHotLoop(): void
    {
        $total = 0;
        $datasets = $this->datasets;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $total += collect($datasets[$i])
                ->map(fn ($n) => $n * 2)
                ->filter(fn ($n) => $n % 3 === 0)
                ->sum();
        }

        $this->sink = $total;
    }

    /** 素の配列処理を含むホットループ全体 */
    public function benchRawArrayHotLoop(): void
    {
        $total = 0;
        $datasets = $this->datasets;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $sum = 0;

            foreach ($datasets[$i] as $n) {
                $value = $n * 2;
                if ($value % 3 === 0) {
                    $sum += $value;
                }
            }

            $total += $sum;
        }

        $this->sink = $total;
    }

    /** 配列関数のパイプラインを含むホットループ全体 */
    public function benchArrayFunctionsHotLoop(): void
    {
        $total = 0;
        $datasets = $this->datasets;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $mapped = \array_map(fn ($n) => $n * 2, $datasets[$i]);
            $filtered = \array_filter($mapped, fn ($n) => $n % 3 === 0);
            $total += \array_sum($filtered);
        }

        $this->sink = $total;
    }

    /** 対象パイプラインを除いた同形の基準ループ */
    public function benchLoopBaseline(): void
    {
        $total = 0;
        $datasets = $this->datasets;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $total += $datasets[$i][0];
        }

        $this->sink = $total;
    }
}
