<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 標準関数でも「コールバックは userland PHP」なので JIT が効きうるか検証。
 *   - array_map / usort の本体は C だが、渡すコールバックは PHP opcode
 *   - 比較用に、コールバックを使わない手書き foreach も計測
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CallbackBench.php --report=aggregate
 *   # JIT OFF:
 *   ... --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
class CallbackBench
{
    private const N = 10000;

    private array $data;
    private array $sortData;

    public function setUp(): void
    {
        $this->data = range(1, self::N);
        // ソート対象は毎回同じ初期状態に戻せるよう保持（疑似ランダム）
        $this->sortData = [];
        $seed = 12345;
        for ($i = 0; $i < self::N; $i++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $this->sortData[] = $seed;
        }
    }

    /** array_map：コールバックで算術（userland） */
    public function benchArrayMap(): void
    {
        array_map(static function (int $n): float {
            return $n * 1.5 + $n - ($n / 3.0);
        }, $this->data);
    }

    /** usort：比較コールバックを O(n log n) 回呼ぶ（userland） */
    public function benchUsort(): void
    {
        $d = $this->sortData; // コピーして毎回未ソート状態から
        usort($d, static function (int $a, int $b): int {
            return ($a % 97) <=> ($b % 97);
        });
    }
}
