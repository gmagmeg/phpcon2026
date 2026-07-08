<?php

declare(strict_types=1);

/**
 * PcreJitBench.php の手続き版。
 *
 * PhpBench のクラス/アトリビュートを使わず、同じワークロードを hrtime() で計測する。
 * 「正規表現を速くしているのは opcache の JIT ではなく PCRE 独自の JIT」を確かめる。
 *
 * 3水準の切り替え（ini を CLI で渡す）:
 *   PCRE JIT ON（既定）:
 *     php pcre_jit_plain.php
 *   PCRE JIT OFF:
 *     php -d pcre.jit=0 pcre_jit_plain.php
 *   opcache JIT OFF（PCRE JIT は残す。これでも速ければ opcache は無関係の証拠）:
 *     php -d opcache.enable_cli=1 -d opcache.jit_buffer_size=0 pcre_jit_plain.php
 */

// 量化子・選択を含み、PCRE JIT の差が出やすいパターン
$pattern = '/(\w+)@(\w+)\.(com|net|org|co\.jp)|(\d{4})-(\d{2})-(\d{2})/';

// 2000 件の入力文字列を用意（PcreJitBench::setUp 相当）
$strings = [];
for ($i = 0; $i < 2000; $i++) {
    $strings[] = "user{$i}_name foo bar baz 2026-06-11 contact user{$i}@example.com end of line {$i}";
}

// PhpBench の Warmup(2)/Revs(50)/Iterations(5) に対応
const WARMUP     = 2;
const REVS       = 50;
const ITERATIONS = 5;

/**
 * $work を REVS 回まわして「1 rev あたりの平均時間(μs)」を求める。
 * これを ITERATIONS 回くり返し、中央値を代表値として返す（phpbench の mode に相当）。
 */
function measure(callable $work): float
{
    for ($w = 0; $w < WARMUP; $w++) {
        $work();
    }

    $perRev = [];
    for ($it = 0; $it < ITERATIONS; $it++) {
        $start = hrtime(true);
        for ($r = 0; $r < REVS; $r++) {
            $work();
        }
        $elapsedNs = hrtime(true) - $start;
        $perRev[] = $elapsedNs / REVS / 1000; // ns → μs, 1 rev あたり
    }

    sort($perRev);
    return $perRev[intdiv(count($perRev), 2)]; // 中央値
}

$benchPregMatch = static function () use ($pattern, $strings): void {
    foreach ($strings as $s) {
        preg_match($pattern, $s, $m);
    }
};

$benchPregMatchAll = static function () use ($pattern, $strings): void {
    foreach ($strings as $s) {
        preg_match_all($pattern, $s, $m);
    }
};

// 実行時の設定を明示（どの水準を測っているか分かるように）
printf("pcre.jit                = %s\n", ini_get('pcre.jit') ?: '(default on)');
printf("opcache.jit             = %s\n", ini_get('opcache.jit') ?: '(n/a)');
printf("opcache.jit_buffer_size = %s\n\n", ini_get('opcache.jit_buffer_size') ?: '(n/a)');

printf("preg_match     ×2000 : %8.1f μs\n", measure($benchPregMatch));
printf("preg_match_all ×2000 : %8.1f μs\n", measure($benchPregMatchAll));
