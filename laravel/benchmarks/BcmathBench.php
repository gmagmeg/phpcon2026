<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * ネイティブ float 演算子 vs bcmath（C実装の任意精度関数）。
 *
 * 同じ「掛けて足す」ループを 2 方式で実装。
 *   - benchNativeFloat : 言語opcode（* +）→ JIT の対象になる
 *   - benchBcmath      : bcmul/bcadd（内部関数）→ JIT 非対象＋文字列ベースで低速
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/BcmathBench.php --report=aggregate
 *   # JIT OFF:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/BcmathBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class BcmathBench
{
    private const N = 1000;
    private const SCALE = 10;

    public function benchNativeFloat(): void
    {
        $sum = 0.0;
        for ($i = 1; $i <= self::N; $i++) {
            $sum += $i * 1.5;
        }
    }

    public function benchBcmath(): void
    {
        $sum = '0';
        for ($i = 1; $i <= self::N; $i++) {
            $sum = bcadd($sum, bcmul((string) $i, '1.5', self::SCALE), self::SCALE);
        }
    }
}
