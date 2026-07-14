<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 主張の検証①: 「軽量な組み込み関数（strlen / count / is_* / intval）は
 * opcache 最適化で専用 opcode（ZEND_STRLEN / ZEND_COUNT / ZEND_TYPE_CHECK 等）に
 * 変換され、JIT がそれをネイティブコードに落とすため呼び出しコストが消える」
 *
 * 検証できる予測:
 *   - \strlen / \count / \is_* / \intval のループは JIT ON で大きく速くなる
 *   - 同じループ形でも重い C 関数（md5）は JIT ON でほぼ変わらない（対照群）
 *   - 裸の strlen()（名前空間内の非修飾呼び出し）は INIT_NS_FCALL_BY_NAME に
 *     コンパイルされ専用 opcode 化されない → \strlen() より遅いはず（前提条件の検証）
 *
 * 注意: JIT OFF（jit_buffer_size=0）でも opcache optimizer は動くため、
 * strlen / count 単体の推定コストは、各設定で対応する
 * benchStrlenBaseline / benchCountBaseline を差し引いて比較する。
 *
 *   # JIT OFF:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InlinedInternalBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":0}'
 *   # tracing JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InlinedInternalBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":"64M","opcache.jit":"tracing"}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class InlinedInternalBench
{
    private const N = 10000;

    /** @var list<string> */
    private array $strings;

    /** @var list<int> strlen と同じループを回す基準計測用 */
    private array $stringLengths;

    /** @var list<list<int>> */
    private array $arrays;

    /** @var list<int> count と同じループを回す基準計測用 */
    private array $arrayCounts;

    /** @var list<int|string|float> */
    private array $mixedValues;

    /** @var list<string> */
    private array $digitStrings;

    /** DCE 防止: 結果をプロパティに残してループが消されないようにする */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->strings = [];
        $this->stringLengths = [];
        $this->arrays = [];
        $this->arrayCounts = [];
        $this->mixedValues = [];
        $this->digitStrings = [];

        for ($i = 0; $i < self::N; $i++) {
            $length = 1 + ($i % 40);
            $arrayCount = 1 + ($i % 8);
            $this->strings[] = \str_repeat('x', $length);
            $this->stringLengths[] = $length;
            $this->arrays[] = \array_fill(0, $arrayCount, $i);
            $this->arrayCounts[] = $arrayCount;
            $this->mixedValues[] = match ($i % 3) {
                0 => $i,
                1 => 'value-' . $i,
                2 => $i * 1.5,
            };
            $this->digitStrings[] = (string) ($i * 13);
        }
    }

    /**
     * \strlen: 完全修飾 → ZEND_STRLEN 化される。
     * 毎回異なる配列要素を渡し、固定値のループ不変化を避ける。
     */
    public function benchStrlen(): void
    {
        $acc = 0;
        $strings = $this->strings;
        for ($i = 0; $i < self::N; $i++) {
            $acc += \strlen($strings[$i]);
        }
        $this->sink = $acc;
    }

    /** strlen 計測と同じ「配列参照 + int 加算」を行う基準ループ */
    public function benchStrlenBaseline(): void
    {
        $acc = 0;
        $lengths = $this->stringLengths;
        for ($i = 0; $i < self::N; $i++) {
            $acc += $lengths[$i];
        }
        $this->sink = $acc;
    }

    /** 裸の strlen: 名前空間フォールバック呼び出し → 専用 opcode 化されない（対比） */
    public function benchStrlenUnqualified(): void
    {
        $acc = 0;
        $strings = $this->strings;
        for ($i = 0; $i < self::N; $i++) {
            $acc += strlen($strings[$i]);
        }
        $this->sink = $acc;
    }

    /**
     * \count: 完全修飾 → ZEND_COUNT 化される。
     * 毎回異なる配列要素を渡し、固定値のループ不変化を避ける。
     */
    public function benchCount(): void
    {
        $acc = 0;
        $arrays = $this->arrays;
        for ($i = 0; $i < self::N; $i++) {
            $acc += \count($arrays[$i]);
        }
        $this->sink = $acc;
    }

    /** count 計測と同じ「配列参照 + int 加算」を行う基準ループ */
    public function benchCountBaseline(): void
    {
        $acc = 0;
        $counts = $this->arrayCounts;
        for ($i = 0; $i < self::N; $i++) {
            $acc += $counts[$i];
        }
        $this->sink = $acc;
    }

    /** 裸の count: 名前空間フォールバック呼び出し → 専用 opcode 化されない（対比） */
    public function benchCountUnqualified(): void
    {
        $acc = 0;
        $arrays = $this->arrays;
        for ($i = 0; $i < self::N; $i++) {
            $acc += count($arrays[$i]);
        }
        $this->sink = $acc;
    }

    /** \is_int / \is_string → ZEND_TYPE_CHECK 化される */
    public function benchTypeCheck(): void
    {
        $acc = 0;
        foreach ($this->mixedValues as $v) {
            if (\is_int($v)) {
                $acc += 1;
            } elseif (\is_string($v)) {
                $acc += 2;
            }
        }
        $this->sink = $acc;
    }

    /** \intval: 単純なキャスト系 */
    public function benchIntval(): void
    {
        $acc = 0;
        foreach ($this->digitStrings as $s) {
            $acc += \intval($s);
        }
        $this->sink = $acc;
    }

    /** 対照群: 重い C 関数（md5）。呼び出しコストが支配的でないため JIT の恩恵はほぼ無いはず */
    public function benchMd5Control(): void
    {
        $acc = 0;
        foreach ($this->strings as $s) {
            $acc += \md5($s)[0] === 'a' ? 1 : 0;
        }
        $this->sink = $acc;
    }
}
