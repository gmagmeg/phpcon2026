<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * カート確定処理（クーポン割引・送料計算）を「手続き的」に実装した版。
 *
 * 連想配列 ＋ switch 分岐だけで完結し、VO クラスには一切依存しない。
 * 送料テーブルもクーポン仕様もこのクラスの const にベタ書きする。
 * 型付き VO 版は CartCheckoutTypedBench を参照（同じ入力・同じ結果）。
 *
 *   # --php-config は JSON オブジェクトで渡す（bare "key: value" は無視され既定が効くので注意）
 *   # JIT ON（tracing）
 *   vendor/bin/phpbench run benchmarks/CartCheckoutArrayBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "tracing"}'
 *   # JIT OFF
 *   vendor/bin/phpbench run benchmarks/CartCheckoutArrayBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": 0}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
class CartCheckoutArrayBench
{
    private const N = 5000;
    private const LINES_PER_CART = 4;

    private const FREE_THRESHOLD = 8000;
    private const FALLBACK_FEE = 2000;

    /** 地域 => [ [重量上限, 送料], ... ]（昇順） */
    private const SHIPPING_TABLE = [
        'north' => [[500, 800], [2000, 1100], [5000, 1500]],
        'central' => [[500, 600], [2000, 900], [5000, 1300]],
        'south' => [[500, 700], [2000, 1000], [5000, 1400]],
    ];

    private const REGIONS = ['north', 'central', 'south'];

    /** クーポン仕様（型付き版の Coupon 群と同一内容・同一並び） */
    private const COUPONS = [
        ['type' => 'fixed', 'amount' => 500],
        ['type' => 'rate', 'percent' => 10],
        ['type' => 'threshold', 'threshold' => 5000, 'amount' => 1200],
    ];

    /**
     * @var list<array{
     *   region:string,
     *   coupon:int,
     *   lines:list<array{unit_price:int, qty:int, weight:int}>
     * }>
     */
    private array $carts;

    /** DCE 防止 */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->carts = [];

        for ($i = 0; $i < self::N; $i++) {
            $region = self::REGIONS[$i % count(self::REGIONS)];
            $couponIndex = $i % count(self::COUPONS);

            $lines = [];
            for ($j = 0; $j < self::LINES_PER_CART; $j++) {
                $unitPrice = 300 + (($i + $j) % 20) * 100; // 300..2200
                $qty = 1 + (($i + $j) % 4);                // 1..4
                $unitWeight = 100 + (($i * $j) % 5) * 150; // 100..700

                $lines[] = [
                    'unit_price' => $unitPrice,
                    'qty' => $qty,
                    'weight' => $unitWeight,
                ];
            }

            $this->carts[] = [
                'region' => $region,
                'coupon' => $couponIndex,
                'lines' => $lines,
            ];
        }
    }

    /** 小計 → 割引（switch 分岐）→ 送料（テーブル引き）→ 確定 を手続きで回す */
    public function benchCheckout(): void
    {
        $sink = 0;

        foreach ($this->carts as $cart) {
            $subtotal = 0;
            $weight = 0;
            foreach ($cart['lines'] as $line) {
                $subtotal += $line['unit_price'] * $line['qty'];
                $weight += $line['weight'] * $line['qty'];
            }

            $spec = self::COUPONS[$cart['coupon']];
            switch ($spec['type']) {
                case 'fixed':
                    $discount = min($spec['amount'], $subtotal);
                    break;
                case 'rate':
                    $discount = intdiv($subtotal * $spec['percent'], 100);
                    break;
                case 'threshold':
                    $discount = $subtotal >= $spec['threshold'] ? $spec['amount'] : 0;
                    break;
                default:
                    $discount = 0;
            }

            $discounted = $subtotal - $discount;

            if ($discounted >= self::FREE_THRESHOLD) {
                $shipping = 0;
            } else {
                $shipping = self::FALLBACK_FEE;
                foreach (self::SHIPPING_TABLE[$cart['region']] as [$maxWeight, $fee]) {
                    if ($weight <= $maxWeight) {
                        $shipping = $fee;
                        break;
                    }
                }
            }

            $sink += $discounted + $shipping;
        }

        $this->sink = $sink;
    }
}
