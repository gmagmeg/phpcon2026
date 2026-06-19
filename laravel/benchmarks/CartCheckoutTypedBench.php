<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * カート確定処理（クーポン割引・送料計算）を「型付き VO」で実装した版。
 *
 * 責務ごとにクラスへ分解する:
 *   Cart / CartLine        小計・総重量の算出
 *   Region                 配送地域（型付き列挙）
 *   Coupon（Fixed/Rate/Threshold）割引額の算出（多態）／ CouponCatalog が集合を提供
 *   ShippingRateTable / ShippingRate  地域×重量の送料データ
 *   ShippingPolicy         送料の算出（無料ライン＋テーブル引き）
 *   CheckoutService        小計→割引→送料→確定のオーケストレーション
 *   CheckoutReceipt        確定結果（小計・割引・送料・請求額）
 *
 * クーポン割引は $coupon->discountFor() の多態 dispatch になり、JIT tracing が
 * その呼び出しを特殊化・インライン化できるかが見どころ。
 * 手続き版は CartCheckoutArrayBench を参照（同じ入力・同じ結果）。
 *
 *   # --php-config は JSON オブジェクトで渡す（bare "key: value" は無視され既定が効くので注意）
 *   # JIT ON（tracing）
 *   vendor/bin/phpbench run benchmarks/CartCheckoutTypedBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "tracing"}'
 *   # JIT OFF
 *   vendor/bin/phpbench run benchmarks/CartCheckoutTypedBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": 0}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
class CartCheckoutTypedBench
{
    private const N = 5000;
    private const LINES_PER_CART = 4;

    private const FREE_THRESHOLD = 8000;
    private const FALLBACK_FEE = 2000;

    /**
     * @var list<array{cart:Cart, coupon:Coupon}>
     */
    private array $carts;

    private CheckoutService $checkoutService;

    /** DCE 防止 */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->carts = [];

        $coupons = (new CouponCatalog())->typed();

        $this->checkoutService = new CheckoutService(new ShippingPolicy(
            new Money(self::FREE_THRESHOLD),
            new ShippingRateTable(),
            new Money(self::FALLBACK_FEE),
        ));

        $regions = Region::cases();

        for ($i = 0; $i < self::N; $i++) {
            $region = $regions[$i % count($regions)];
            $couponIndex = $i % count($coupons);

            $lines = [];
            for ($j = 0; $j < self::LINES_PER_CART; $j++) {
                $lines[] = CartLineFactory::create($i, $j);
            }

            $this->carts[] = [
                'cart' => new Cart($lines, $region),
                'coupon' => $coupons[$couponIndex],
            ];
        }
    }

    /** CheckoutService に委譲するだけ（中身は VO 群が担う・多態 dispatch） */
    public function benchCheckout(): void
    {
        $sink = 0;

        foreach ($this->carts as $entry) {
            $receipt = $this->checkoutService->checkout($entry['cart'], $entry['coupon']);
            $sink += $receipt->total->amount();
        }

        $this->sink = $sink;
    }
}
