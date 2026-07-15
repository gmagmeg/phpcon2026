<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 四則演算とループ制御そのものに対する JIT の効果を比較するベンチマーク。
 *
 * - 四則演算: ユーザーランドの float 演算だけを繰り返す
 * - 整数除算: intdiv() で整数のまま除算を繰り返す
 * - for / foreach: 本体を空にし、ループ制御だけを測る
 * - foreach の対象配列は setUp() で作るため、配列生成コストは含めない
 *
 * 各四則演算の値には for ループのコストも含まれる。演算だけを見たい場合は、
 * 同じ JIT 設定の benchFloatArithmeticBaseline を未丸め値のまま差し引く。
 * benchIntegerDivision は、同じ計算のうち intdiv() だけを除いた
 * benchIntegerDivisionBaseline を差し引く。
 *
 * # JIT OFF
 * docker compose exec app vendor/bin/phpbench run benchmarks/ArithmeticAndLoopBench.php --report=aggregate \
 *   --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "0"}'
 *
 * # JIT function
 * docker compose exec app vendor/bin/phpbench run benchmarks/ArithmeticAndLoopBench.php --report=aggregate \
 *   --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "function"}'
 *
 * # JIT tracing
 * docker compose exec app vendor/bin/phpbench run benchmarks/ArithmeticAndLoopBench.php --report=aggregate \
 *   --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "tracing"}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class ArithmeticAndLoopBench
{
    private const N = 10000;

    /** @var list<null> foreach 用。計測中の配列生成を避ける。 */
    private array $loopValues;

    /** 演算結果を残し、計測対象の演算を不要な計算にしない。 */
    private float $sink = 0.0;

    /** 整数除算の結果を残す。 */
    private int $integerSink = 0;

    public function setUp(): void
    {
        $this->loopValues = array_fill(0, self::N, null);
    }

    /** 加算（+） */
    public function benchAddition(): void
    {
        $value = 0.0;
        for ($i = 0; $i < self::N; $i++) {
            $value += 1.0001;
        }
        $this->sink = $value;
    }

    /** 減算（-） */
    public function benchSubtraction(): void
    {
        $value = 10000.0;
        for ($i = 0; $i < self::N; $i++) {
            $value -= 1.0001;
        }
        $this->sink = $value;
    }

    /** 乗算（*）。オーバーフローしない定数を使う。 */
    public function benchMultiplication(): void
    {
        $value = 1.0;
        for ($i = 0; $i < self::N; $i++) {
            $value *= 1.000001;
        }
        $this->sink = $value;
    }

    /** 除算（/）。0 除算にならない定数を使う。 */
    public function benchDivision(): void
    {
        $value = 1.0;
        for ($i = 0; $i < self::N; $i++) {
            $value /= 1.000001;
        }
        $this->sink = $value;
    }

    /** float 四則演算用の基準ループ。初期化と結果保存も計測対象と揃える。 */
    public function benchFloatArithmeticBaseline(): void
    {
        $value = 0.0;
        for ($i = 0; $i < self::N; $i++) {
        }
        $this->sink = $value;
    }

    /** 整数除算（intdiv）。ループごとに値を変え、整数の結果依存を維持する。 */
    public function benchIntegerDivision(): void
    {
        $value = 1000000000;
        for ($i = 0; $i < self::N; $i++) {
            $value = \intdiv($value + $i, 3);
        }
        $this->integerSink = $value;
    }

    /** 整数除算用の基準ループ。intdiv() 以外の加算と代入を揃える。 */
    public function benchIntegerDivisionBaseline(): void
    {
        $value = 1000000000;
        for ($i = 0; $i < self::N; $i++) {
            $value = $value + $i;
        }
        $this->integerSink = $value;
    }

    /** for の制御コスト。本体は意図的に空。 */
    public function benchForEmpty(): void
    {
        for ($i = 0; $i < self::N; $i++) {
        }
    }

    /** foreach の制御コスト。本体は意図的に空。 */
    public function benchForeachEmpty(): void
    {
        foreach ($this->loopValues as $_) {
        }
    }
}
