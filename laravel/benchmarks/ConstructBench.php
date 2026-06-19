<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 代入の計測（イミュータブル = コンストラクタ代入）。
 *
 * イミュータブルな値オブジェクトの代入は「コンストラクタでプロパティに 1 回入れる」
 * に集約される。型付き readonly vs 型なしで生成（= コンストラクタ代入）コストを比較。
 *
 * 読み筋:
 *   - JIT ON で benchTyped は代入時の型検証が省かれ、int は refcount も不要なので素のストアになる。
 *   - benchLoose は汎用代入パスが残る → 差は「型を付けたかどうか」。
 *   - 注意: new のアロケーションは両者共通かつ JIT でも消えない。差は代入パスぶんに限られる。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/ConstructBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/ConstructBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\Warmup(2)]
#[Bench\Revs(1000000)]
#[Bench\Iterations(5)]
class ConstructBench
{
    public function benchTyped(): void
    {
        new TypedPoint(1, 2);
    }

    public function benchLoose(): void
    {
        new LoosePoint(1, 2);
    }
}
