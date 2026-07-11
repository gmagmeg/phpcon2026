<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * カート操作シナリオで、ミュータブル / イミュータブルの JIT の効き方を比べる。
 *
 * シナリオ（1 回分）:
 *   1. カートに商品を 3 点入れる
 *   2. カートから商品を 1 点出す
 *   3. 合計金額を出す
 *
 * 2 水準:
 *   ミュータブル   … 内部配列を破壊的更新。new はカート生成の 1 回だけ。
 *   イミュータブル … add / removeLast のたびに new ImmutableCart、
 *                     さらに add では new ItemInCart も積まれる
 *                     （1 シナリオで cart×4 + wrapper×3 = 7 alloc）。
 *
 * 読み筋: MoneyBench と同じく「new の位置と回数」が JIT の効き幅を決める。
 * 商品（ScenarioItem）は setUp で使い回し、カート側のアロケーション差だけを測る。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CartScenarioBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CartScenarioBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
class CartScenarioBench
{
    private const N = 1000;

    /** @var list<ScenarioItem> */
    private array $items;

    /** DCE 防止: 結果をプロパティに残してループが消されないようにする */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->items = [
            new ScenarioItem(1200),
            new ScenarioItem(350),
            new ScenarioItem(4800),
        ];
    }

    public function benchMutableCart(): void
    {
        [$a, $b, $c] = $this->items;
        $acc = 0;

        for ($i = 0; $i < self::N; $i++) {
            $cart = new MutableCart();
            $cart->add($a);
            $cart->add($b);
            $cart->add($c);
            $cart->removeLast();
            $acc += $cart->totalAmount();
        }

        $this->sink = $acc;
    }

    public function benchImmutableCart(): void
    {
        [$a, $b, $c] = $this->items;
        $acc = 0;

        for ($i = 0; $i < self::N; $i++) {
            $cart = new ImmutableCart();
            $cart = $cart->add($a);
            $cart = $cart->add($b);
            $cart = $cart->add($c);
            $cart = $cart->removeLast();
            $acc += $cart->totalAmount();
        }

        $this->sink = $acc;
    }
}
