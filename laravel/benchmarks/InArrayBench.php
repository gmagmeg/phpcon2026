<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * in_array（線形探索 O(n)） vs isset / array_key_exists（ハッシュ参照 O(1)）。
 *
 * いずれも C 実装の内部関数／専用 opcode なので、OPcache・JIT の ON/OFF では
 * ほとんど変わらない。出てくる差は完全に「アルゴリズム（計算量）」由来であることを示す。
 *
 *   # ON（既定：opcache 有効）
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InArrayBench.php --report=aggregate
 *   # OFF（opcache 無効）
 *   docker compose exec app vendor/bin/phpbench run benchmarks/InArrayBench.php --report=aggregate \
 *     --php-config='opcache.enable_cli: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(2000)]
#[Bench\Iterations(5)]
class InArrayBench
{
    private const N = 10000;

    /** @var list<int> 値の配列（in_array 用） */
    private array $values;

    /** @var array<int,int> 値をキーにした連想配列（isset / array_key_exists 用） */
    private array $lookup;

    private int $needle;

    public function setUp(): void
    {
        $this->values = range(1, self::N);
        $this->lookup = array_flip($this->values);
        // 末尾要素を探索 → in_array の最悪計算量（全走査）を引き出す
        $this->needle = self::N;
    }

    /** in_array：線形探索 O(n) */
    public function benchInArray(): void
    {
        in_array($this->needle, $this->values, true);
    }

    /** isset：ハッシュ参照 O(1) */
    public function benchIsset(): void
    {
        isset($this->lookup[$this->needle]);
    }

    /** array_key_exists：ハッシュ参照 O(1) */
    public function benchArrayKeyExists(): void
    {
        array_key_exists($this->needle, $this->lookup);
    }
}
