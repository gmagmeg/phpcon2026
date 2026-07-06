<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * ★実践: 型付き値オブジェクト演算は JIT で大きく改善する（本トークの核）。
 *
 * 同じ「加算 → 2倍」を 3 水準で実装し、JIT OFF/ON で読み筋を比べる:
 *   A: 生スカラー演算   … OPCODE そのもの（到達できる上限の目安）
 *   B: 型付き Money     … JIT ON で大きく改善し A に肉薄
 *   C: 型なし MoneyLoose … 型ガードが残り改善は鈍い
 *
 * 結論: B と C の差（= 型を付けたかどうか）が JIT ON で開く。
 * 注意: イミュータブルな値オブジェクトは毎演算で new するため、
 *       アロケーション / GC コストは JIT でも消えず、A に完全一致はしない。
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

    /** A: 生スカラー演算（add → multiply 相当を素の int で） */
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

    /** C: 型付き（イミュータブル）Money の add / multiply をループ */
    public function benchTypedMoney(): void
    {
        $acc = 0;
        for ($i = 0; $i < self::N; $i++) {
            $m = new Money($i);
            $acc += $m->add($m)->multiply(2)->amount();
        }
        $this->sink = $acc;
    }

    /** C: 型なし MoneyLoose で B と同等処理 */
    public function benchLooseMoney(): void
    {
        $acc = 0;
        for ($i = 0; $i < self::N; $i++) {
            $m = new MoneyLoose($i);
            $acc += $m->add($m)->multiply(2)->amount();
        }
        $this->sink = $acc;
    }
}
