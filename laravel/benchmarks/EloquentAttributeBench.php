<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * ① Eloquent 属性アクセス：計算アクセサ vs キャストアクセサ。
 *
 * 構文上はどちらも「プロパティ 1 回読むだけ」。だが中身が割れる:
 *   E1 計算アクセサ total  : getAttribute → Attribute アクセサ → price * qty（純 PHP 算術）
 *   E2 datetime キャスト   : getAttribute → castAttribute → asDateTime → Carbon 構築（C 側）
 *
 * JIT は E1 のディスパッチ＋算術を特殊化できるが、E2 の Carbon 構築（日付パース＝
 * zend/C 側＋オブジェクト生成）には届かない。「アクセサだから遅い」ではなく
 * 「アクセサが何をしているか」で JIT の効き幅が決まる、を数字で見る。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/EloquentAttributeBench.php --report=aggregate
 *   # JIT OFF: --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100000)]
#[Bench\Iterations(5)]
class EloquentAttributeBench extends LaravelBench
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

    /** E1: 計算アクセサ（price * qty ＝ 純 PHP 算術） */
    public function benchComputedAccessor(): void
    {
        $this->sink = $this->order->total;
    }

    /** E2: datetime キャスト（Carbon 構築 ＝ C 側） */
    public function benchDatetimeCast(): void
    {
        $this->sink = $this->order->due_at;
    }
}
