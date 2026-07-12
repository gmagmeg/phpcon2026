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
 * OFF/ON の差分は「専用 opcode → ネイティブ化」の寄与だけを見ている。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InlinedInternalBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InlinedInternalBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
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

    /** @var list<list<int>> */
    private array $arrays;

    /** @var list<int|string|float> */
    private array $mixedValues;

    /** @var list<string> */
    private array $digitStrings;

    /** 配列イテレーションを挟まず「呼び出しそのもの」を測るための固定値 */
    private string $fixedString;

    /** @var array<int, int> */
    private array $fixedArray;

    /** DCE 防止: 結果をプロパティに残してループが消されないようにする */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->strings = [];
        $this->arrays = [];
        $this->mixedValues = [];
        $this->digitStrings = [];

        for ($i = 0; $i < self::N; $i++) {
            $this->strings[] = \str_repeat('x', 1 + ($i % 40));
            $this->arrays[] = \array_fill(0, 1 + ($i % 8), $i);
            $this->mixedValues[] = match ($i % 3) {
                0 => $i,
                1 => 'value-' . $i,
                2 => $i * 1.5,
            };
            $this->digitStrings[] = (string) ($i * 13);
        }

        // strlen / count は配列を歩かず、この固定値を N 回呼ぶ
        $this->fixedString = \str_repeat('x', 20);
        $this->fixedArray = \array_fill(0, 4, 0);
    }

    /** \strlen: 完全修飾 → ZEND_STRLEN 化される（JIT で消えるはず） */
    public function benchStrlen(): void
    {
        $acc = 0;
        $s = $this->fixedString;
        for ($i = 0; $i < self::N; $i++) {
            $acc += \strlen($s);
        }
        $this->sink = $acc;
    }

    /** 裸の strlen: 名前空間フォールバック呼び出し → 専用 opcode 化されない（対比） */
    public function benchStrlenUnqualified(): void
    {
        $acc = 0;
        $s = $this->fixedString;
        for ($i = 0; $i < self::N; $i++) {
            $acc += strlen($s);
        }
        $this->sink = $acc;
    }

    /** \count → ZEND_COUNT 化される */
    public function benchCount(): void
    {
        $acc = 0;
        $a = $this->fixedArray;
        for ($i = 0; $i < self::N; $i++) {
            $acc += \count($a);
        }
        $this->sink = $acc;
    }

    /** 裸の count: 名前空間フォールバック呼び出し → 専用 opcode 化されない（対比） */
    public function benchCountUnqualified(): void
    {
        $acc = 0;
        $a = $this->fixedArray;
        for ($i = 0; $i < self::N; $i++) {
            $acc += count($a);
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
