<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 主張の検証②: 「sqrt() など一部の数学関数は JIT で FPU/SSE 命令に直接落ちる。
 * 演算子（+ / *）と型推論の組み合わせが主役で、単純な数学関数もその流れに乗る」
 *
 * 検証できる予測:
 *   - \sqrt() を含む算術ループは JIT ON で劇的に速くなる
 *   - 直接命令化されにくい数学関数（\sin）は通常の内部関数呼び出しに
 *     とどまり、効き幅が小さいはず（対照群）
 *   - 差が出なければ「sqrt が SSE に直接落ちる」という主張は要修正
 *     （効いているのはループと演算子の JIT だけ、ということになる）
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/MathJitBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/MathJitBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class MathJitBench
{
    private const N = 10000;

    /** DCE 防止: 結果をプロパティに残してループが消されないようにする */
    private float $sink = 0.0;

    /** \sqrt を含む算術ループ（型推論 + 演算子 + sqrt） */
    public function benchSqrtLoop(): void
    {
        $acc = 0.0;
        for ($i = 0; $i < self::N; $i++) {
            $acc += \sqrt($i * 1.5);
        }
        $this->sink = $acc;
    }

    /** 対照群: \sin（通常の内部関数呼び出しにとどまる想定） */
    public function benchSinControl(): void
    {
        $acc = 0.0;
        for ($i = 0; $i < self::N; $i++) {
            $acc += \sin($i * 0.001);
        }
        $this->sink = $acc;
    }

    /** 参考: 関数を使わない純粋な演算子ループ（JIT が最も効く形の基準線） */
    public function benchArithmeticBaseline(): void
    {
        $acc = 0.0;
        for ($i = 0; $i < self::N; $i++) {
            $acc += ($i * 1.5 + 0.25) * 0.5;
        }
        $this->sink = $acc;
    }
}
