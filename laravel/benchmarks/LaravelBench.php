<?php

declare(strict_types=1);

namespace App\Benchmarks;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * Laravel のメソッドをベンチするための基底クラス。
 *
 * setUp() でアプリを boot し、コンテナ／ファサードを使える状態にする。
 * PHPBench は iteration ごとに別プロセスで動き、setUp は revs(内側ループ)の
 * 外で1回だけ走るため、boot コストは計測値に乗らない。
 */
abstract class LaravelBench
{
    protected Application $app;

    public function setUp(): void
    {
        $this->app = require __DIR__ . '/../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
    }
}
