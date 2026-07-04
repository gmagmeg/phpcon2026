<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * Eloquent モデルの属性アクセス：アロー vs 配列アクセスの 2 記法を計測。
 *
 * モデルは ArrayAccess を実装しているので、同じカラム値を 2 通りで読める:
 *   P1 $model->price    : マジック __get → getAttribute
 *   P2 $model['price']  : ArrayAccess::offsetGet → getAttribute
 *
 * どちらも最終的に getAttribute（getAttributeValue → transformModelValue で
 * 「mutator あるか？」「cast あるか？」を判定）へ合流する。入口が __get か
 * offsetGet かの違いだけ。記法で差が出るか、そして JIT OFF / function /
 * tracing でどれだけ変わるかを見る。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/EloquentPropertyBench.php --report=aggregate
 *   # JIT OFF: --php-config='opcache.jit_buffer_size: 0'
 *   # function: --php-config='opcache.jit_buffer_size: 64M' --php-config='opcache.jit: function'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100000)]
#[Bench\Iterations(5)]
class EloquentPropertyBench extends LaravelBench
{
    private BenchOrder $order;

    private mixed $sink = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->order = new BenchOrder();
        $this->order->setRawAttributes([
            'price' => 1200,
            'qty' => 3,
            'due_at' => '2026-07-04 12:00:00',
        ]);
    }

    /** P1: アロー記法：$model->property（マジック __get → getAttribute） */
    public function benchArrowAccess(): void
    {
        $this->sink = $this->order->price;
    }

    /** P2: 配列記法：$model['property']（ArrayAccess::offsetGet → getAttribute） */
    public function benchOffsetAccess(): void
    {
        $this->sink = $this->order['price'];
    }
}
