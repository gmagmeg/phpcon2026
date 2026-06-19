<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * JIT mode（function vs tracing）の違いを実証するベンチ。
 *
 * アロケーションを排し（インスタンスは setUp で 1 回だけ生成）、
 * ホットループから「プロパティをメソッド経由で読んで加算するだけ」を回す。
 * これにより MoneyBench で支配的だった new / GC コストを取り除き、
 *   - JIT mode の差（function は関数単位 compile、tracing はループ trace に
 *     value() をインライン化して特殊化）
 *   - 型の差（型付き B は int 特殊化、型なし C はガードが残る）
 * の 2 つだけを浮かび上がらせる。
 *
 *   # JIT OFF:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/HotLoopBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 *   # function JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/HotLoopBench.php --report=aggregate \
 *     --php-config='opcache.jit: function'
 *   # tracing JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/HotLoopBench.php --report=aggregate \
 *     --php-config='opcache.jit: tracing'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
class HotLoopBench
{
    private const N = 10000;

    private TypedBox $typed;

    private LooseBox $loose;

    private int $raw = 7;

    /** DCE 防止 */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->typed = new TypedBox(7);
        $this->loose = new LooseBox(7);
    }

    /** A: 生スカラー（プロパティを直接読んで加算・メソッド呼び出しなし） */
    public function benchScalar(): void
    {
        $sum = 0;
        $v = $this->raw;
        for ($i = 0; $i < self::N; $i++) {
            $sum += $v;
        }
        $this->sink = $sum;
    }

    /** B: 型付き TypedBox::value() をホットループから呼ぶ（アロケーションなし） */
    public function benchTyped(): void
    {
        $sum = 0;
        $box = $this->typed;
        for ($i = 0; $i < self::N; $i++) {
            $sum += $box->value();
        }
        $this->sink = $sum;
    }

    /** C: 型なし LooseBox::value() で B と同等処理 */
    public function benchLoose(): void
    {
        $sum = 0;
        $box = $this->loose;
        for ($i = 0; $i < self::N; $i++) {
            $sum += $box->value();
        }
        $this->sink = $sum;
    }
}
