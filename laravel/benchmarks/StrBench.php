<?php

declare(strict_types=1);

namespace App\Benchmarks;

use Illuminate\Support\Str;
use PhpBench\Attributes as Bench;

/**
 * Laravel の Str ヘルパ vs 素の関数。
 * Str:: はファサード／コンテナ解決を挟むため、薄いとはいえオーバーヘッドがある。
 *
 * 実行:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/StrBench.php --report=default
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(5000)]
#[Bench\Iterations(5)]
class StrBench extends LaravelBench
{
    private string $text = 'The quick brown fox jumps over the lazy dog';

    public function benchStrSlug(): void
    {
        Str::slug($this->text);
    }

    public function benchStrContainsHelper(): void
    {
        Str::contains($this->text, 'fox');
    }

    public function benchNativeStrContains(): void
    {
        str_contains($this->text, 'fox');
    }
}
