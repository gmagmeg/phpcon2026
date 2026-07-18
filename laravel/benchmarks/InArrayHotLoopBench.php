<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * in_array / isset / array_key_exists を PHP のホットループ内で繰り返すベンチ。
 *
 * InArrayBench が 1 呼び出し単位のコストを測るのに対し、このベンチでは
 * ループ制御を含む実用的な繰り返し処理全体への JIT 効果を測る。
 * 毎回異なる検索値（ヒットとミスを交互）を使い、ループ不変な呼び出しを避ける。
 *
 * benchLoopBaseline は同じ「配列参照 + 分岐 + 加算」を行う基準ループ。
 * 対象処理部分を推定する場合は、同じ JIT 設定の計測値を未丸めのまま差し引く。
 * ホットループ全体を比較する場合は、各 bench* の計測値をそのまま使う。
 *
 *   # JIT OFF:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InArrayHotLoopBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":0}'
 *   # function JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InArrayHotLoopBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":"64M","opcache.jit":"function"}'
 *   # tracing JIT:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InArrayHotLoopBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli":1,"opcache.jit_buffer_size":"64M","opcache.jit":"tracing"}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(10)]
#[Bench\Iterations(5)]
class InArrayHotLoopBench
{
    private const VALUE_COUNT = 10000;

    private const LOOP_COUNT = 10000;

    /** @var list<int> 値の配列（in_array 用） */
    private array $values;

    /** @var array<int, int> 値をキーにした連想配列（isset / array_key_exists 用） */
    private array $lookup;

    /** @var list<int> ヒットとミスを交互に並べた検索値 */
    private array $needles;

    /** DCE 防止: ヒット数をプロパティに残す */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->values = range(1, self::VALUE_COUNT);
        $this->lookup = array_flip($this->values);
        $this->needles = [];

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $this->needles[] = $i % 2 === 0
                ? ($i >> 1) + 1
                : self::VALUE_COUNT + $i;
        }
    }

    /** in_array を含むホットループ全体 */
    public function benchInArrayHotLoop(): void
    {
        $hits = 0;
        $needles = $this->needles;
        $values = $this->values;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            if (\in_array($needles[$i], $values, true)) {
                $hits++;
            }
        }

        $this->sink = $hits;
    }

    /** isset を含むホットループ全体 */
    public function benchIssetHotLoop(): void
    {
        $hits = 0;
        $needles = $this->needles;
        $lookup = $this->lookup;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            if (isset($lookup[$needles[$i]])) {
                $hits++;
            }
        }

        $this->sink = $hits;
    }

    /** array_key_exists を含むホットループ全体 */
    public function benchArrayKeyExistsHotLoop(): void
    {
        $hits = 0;
        $needles = $this->needles;
        $lookup = $this->lookup;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            if (\array_key_exists($needles[$i], $lookup)) {
                $hits++;
            }
        }

        $this->sink = $hits;
    }

    /** 対象処理を除いた同形の基準ループ */
    public function benchLoopBaseline(): void
    {
        $hits = 0;
        $needles = $this->needles;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            if ($needles[$i] <= self::VALUE_COUNT) {
                $hits++;
            }
        }

        $this->sink = $hits;
    }
}
