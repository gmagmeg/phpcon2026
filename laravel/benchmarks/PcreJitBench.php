<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 正規表現 preg_* の「もう一つの JIT」= PCRE JIT を検証。
 *
 *   - preg_match は C(PCRE)実装。opcache の JIT は効かない。
 *   - ただし PCRE は独自の JIT を持ち、ini の pcre.jit（既定ON）で制御する。
 *
 * 比較に使う ini:
 *   PCRE JIT ON  : 既定
 *   PCRE JIT OFF : --php-config='pcre.jit: 0'
 *   opcache JIT OFF（PCRE JITは残す）: --php-config='opcache.jit_buffer_size: 0'
 *     → これでも速いままなら「効いているのは opcache ではなく PCRE JIT」の証拠
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/PcreJitBench.php --report=aggregate
 *   docker compose exec app vendor/bin/phpbench run benchmarks/PcreJitBench.php --report=aggregate --php-config='pcre.jit: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(50)]
#[Bench\Iterations(5)]
class PcreJitBench
{
    private array $strings;

    // 量化子・選択を含み、PCRE JIT の差が出やすいパターン
    private string $pattern = '/(\w+)@(\w+)\.(com|net|org|co\.jp)|(\d{4})-(\d{2})-(\d{2})/';

    public function setUp(): void
    {
        $this->strings = [];
        for ($i = 0; $i < 2000; $i++) {
            $this->strings[] = "user{$i}_name foo bar baz 2026-06-11 contact user{$i}@example.com end of line {$i}";
        }
    }

    public function benchPregMatch(): void
    {
        foreach ($this->strings as $s) {
            preg_match($this->pattern, $s, $m);
        }
    }

    public function benchPregMatchAll(): void
    {
        foreach ($this->strings as $s) {
            preg_match_all($this->pattern, $s, $m);
        }
    }
}
