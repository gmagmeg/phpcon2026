<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 値オブジェクトの等価判定：getter 直比較 vs equal() メソッド（DDD 風）。
 *
 * ドメイン: 振替の「金額の一致判定（Money）」。同じ等価判定を 2 通りで書く。
 *
 *   D1 getter 直比較 : $priceA->amount() === $priceB->amount()
 *                      呼び出し側で getter を 2 回叩き、生の int を === で突き合わせる
 *   D2 equal() 呼び出し: $priceA->equal($priceB)
 *                      VO にカプセル化した等価判定。中身は int 同士の ===
 *
 * 型付き VO 同士なら、JIT は equal() をインライン化し、getter アクセスも
 * プロパティのスロットアクセスに落とす。結果、D2 は D1 と同じ 1 命令の
 * 整数比較に潰れ、メソッドで包んでも速度差は出ない。
 *
 * 注意:
 *   - 値はプロパティ経由で渡す（リテラル直書きだと定数畳み込みされ、比較自体が消えうる）。
 *   - 結果も $sink プロパティに残して DCE を防ぐ。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CompareBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CompareBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(10000000)]
#[Bench\Iterations(5)]
class CompareBench
{
    /** 型付き VO：金額（内容一致・別インスタンス） */
    private Money $priceA;

    private Money $priceB;

    private bool $sink = false;

    public function setUp(): void
    {
        $this->priceA = new Money(12345);
        $this->priceB = new Money(12345);
    }

    /** D1: getter 直比較（int 同士の ===） */
    public function benchGetterIdentical(): void
    {
        $this->sink = $this->priceA->amount() === $this->priceB->amount();
    }

    /** D2: equal() メソッド呼び出し（VO にカプセル化した等価判定） */
    public function benchEqualMethod(): void
    {
        $this->sink = $this->priceA->equal($this->priceB);
    }
}
