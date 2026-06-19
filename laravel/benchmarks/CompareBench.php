<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 比較演算子の計測（== vs ===）。
 *
 * 結論: JIT は型が揃えば == / === の差を消す。差が残るのは coercion のときだけ。
 *   D1 int === int      : 1 命令の整数比較に特殊化
 *   D2 int == int       : D1 と同じ命令に潰れ差が消える
 *   D3 int == 数値文字列 : 突出して遅い・JIT でも縮まらない（強制変換は C 側）
 *   D4 int === string   : 型不一致で即 false、安い
 *
 * 注意:
 *   - 値はプロパティ経由で渡す（リテラル直書きだと定数畳み込みされ、比較自体が消えうる）。
 *   - 結果も $sink プロパティに残して DCE を防ぐ。
 *   - == の正しさの落とし穴（0 == "a" 等）は性能とは別軸。混ぜない。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CompareBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CompareBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\Warmup(2)]
#[Bench\Revs(10000000)]
#[Bench\Iterations(5)]
class CompareBench
{
    private int $a = 12345;

    private int $b = 12345;

    private string $s = '12345';

    private bool $sink = false;

    /** D1: int === int */
    public function benchIdenticalIntInt(): void
    {
        $this->sink = $this->a === $this->b;
    }

    /** D2: int == int */
    public function benchEqualIntInt(): void
    {
        $this->sink = $this->a == $this->b;
    }

    /** D3: int == 数値文字列（強制変換） */
    public function benchEqualIntNumStr(): void
    {
        $this->sink = $this->a == $this->s;
    }

    /** D4: int === string（型不一致 → false） */
    public function benchIdenticalIntStr(): void
    {
        $this->sink = $this->a === $this->s;
    }
}
