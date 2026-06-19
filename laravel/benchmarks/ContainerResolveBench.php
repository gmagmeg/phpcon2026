<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * ② サービスコンテナ解決：autowire（リフレクション） vs クロージャ束縛。
 *
 *   C1 autowire       : make(BenchService::class)。束縛が無いのでコンテナが
 *                       ReflectionClass/ReflectionParameter でコンストラクタ引数を
 *                       解決し、依存（BenchDependency）まで再帰生成する。
 *   C2 クロージャ束縛  : 事前に new を書いたクロージャを束縛。make はそれを実行するだけ。
 *
 * リフレクションは C 側実装なので JIT ON/OFF でほぼ横ばい。クロージャ経路（PHP ランド）
 * のほうが JIT の恩恵を受ける。「DI 解決（フレームワークの glue）は JIT で速くならない」
 * ＝ 解決結果はキャッシュ／per-request で make しない、という設計判断に接続する。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/ContainerResolveBench.php --report=aggregate
 *   # JIT OFF: --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(20000)]
#[Bench\Iterations(5)]
class ContainerResolveBench extends LaravelBench
{
    private mixed $sink = null;

    public function setUp(): void
    {
        parent::setUp();

        // C2 用：リフレクションを介さない生成をクロージャで束縛
        $this->app->bind('bench.closure', fn () => new BenchService(new BenchDependency()));
    }

    /** C1: autowire（Reflection でコンストラクタ引数を解決） */
    public function benchAutowireReflection(): void
    {
        $this->sink = $this->app->make(BenchService::class);
    }

    /** C2: クロージャ束縛（Reflection なし・クロージャ実行のみ） */
    public function benchClosureBinding(): void
    {
        $this->sink = $this->app->make('bench.closure');
    }
}
