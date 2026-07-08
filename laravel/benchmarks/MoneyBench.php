<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * ★実践: 値オブジェクト演算が JIT でどこまで速くなるかを、
 * 「オブジェクト生成をループ外へ出せるか（new の位置）」で比べる（本トークの核）。
 *
 * 同じ「加算 → 2倍」を 3 水準で実装する:
 *   A: 手続き（生スカラー）  … OPCODE そのもの。到達できる速度の上限の目安。
 *   B: ミュータブル Money    … new はループ外で 1 度だけ。中は破壊的更新（ループ内 new なし）。
 *   C: イミュータブル Money  … add / multiply が毎回 new する（ループ内で生成が発生）。
 *
 * 読み筋:
 *   B（ミュータブル）は alloc がループ外に出るため、JIT ON で A（手続き）に肉薄する。
 *   C（イミュータブル）は毎演算の new = alloc/GC コストが JIT でも消えず、改善は鈍い。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/MoneyBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/MoneyBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\Warmup(2)]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
class MoneyBench
{
    private const N = 1000;

    /** DCE 防止: 結果をプロパティに残してループが消されないようにする */
    private int $sink = 0;

    /** A: 手続き（生スカラー演算。add → multiply 相当を素の int で） */
    public function benchScalar(): void
    {
        $acc = 0;
        for ($i = 0; $i < self::N; $i++) {
            $acc += ($i + $i) * 2;
        }
        $this->sink = $acc;
    }

    /**
     * B: ミュータブル Money。オブジェクトはループ外で 1 度だけ生成し、
     * 中は破壊的更新で回す（ループ内で new が発生しない）。
     */
    public function benchMutableMoney(): void
    {
        $acc = 0;
        $m = new MutableMoney(0);
        for ($i = 0; $i < self::N; $i++) {
            $m->set($i)->add($m)->multiply(2);
            $acc += $m->amount();
        }
        $this->sink = $acc;
    }

    /** C: イミュータブル Money。add / multiply が毎回 new するためループ内で生成が発生 */
    public function benchImmutableMoney(): void
    {
        $acc = 0;
        for ($i = 0; $i < self::N; $i++) {
            $m = new Money($i);
            $acc += $m->add($m)->multiply(2)->amount();
        }
        $this->sink = $acc;
    }
}
