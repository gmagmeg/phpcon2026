<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 型付き版（CartCheckoutTypedBench）の「低アロケーション」variant。
 *
 * 計測ループ内での new を極力ゼロにする:
 *   - 明細（LeanCartLine）・クーポン（LeanCoupon）は setUp で一度だけ生成
 *   - 小計は再利用する 1 個のミュータブル accumulator（MutableSubtotal）に積算
 *   - 割引・送料は int を返す（Money を作らない）→ ただし多態 dispatch は維持
 *   - 送料テーブルは既存 ShippingRateTable を読むだけ（fee は getter＝生成なし）
 *
 * 免除条件：ミュータブルを許可（immutable Money の再生成をやめる）。
 * これにより「JIT が届かない C 側 alloc」を削ったとき、型付きコードが
 * どこまで速くなるかを、CartCheckoutTypedBench と対比して見る。
 * 入力・結果は他の 2 版（Array / Typed）と一致する。
 *
 *   # --php-config は JSON オブジェクトで渡す（bare "key: value" は無視され既定が効くので注意）
 *   # JIT ON（tracing）
 *   vendor/bin/phpbench run benchmarks/CartCheckoutTypedLeanBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "tracing"}'
 *   # JIT OFF
 *   vendor/bin/phpbench run benchmarks/CartCheckoutTypedLeanBench.php --report=aggregate \
 *     --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": 0}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
class CartCheckoutTypedLeanBench
{
    private const N = 10000;
    private const LINES_PER_CART = 4;

    private const FREE_THRESHOLD = 8000;
    private const FALLBACK_FEE = 2000;

    /**
     * @var list<array{region:Region, coupon:LeanCoupon, lines:list<LeanCartLine>}>
     */
    private array $carts;

    private ShippingRateTable $rates;

    /** ループ内で使い回すミュータブルな小計（インスタンスは 1 個だけ） */
    private MutableSubtotal $subtotal;

    /** DCE 防止 */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->carts = [];
        $this->rates = new ShippingRateTable();
        $this->subtotal = new MutableSubtotal();

        $coupons = [
            new LeanFixedCoupon(500),
            new LeanRateCoupon(10),
            new LeanThresholdCoupon(5000, 1200),
        ];

        $regions = Region::cases();

        for ($i = 0; $i < self::N; $i++) {
            $region = $regions[$i % count($regions)];
            $couponIndex = $i % count($coupons);

            $lines = [];
            for ($j = 0; $j < self::LINES_PER_CART; $j++) {
                $unitPrice = 300 + (($i + $j) % 20) * 100; // 300..2200
                $qty = 1 + (($i + $j) % 4);                // 1..4
                $unitWeight = 100 + (($i * $j) % 5) * 150; // 100..700

                $lines[] = new LeanCartLine($unitPrice, $qty, $unitWeight);
            }

            $this->carts[] = [
                'region' => $region,
                'coupon' => $coupons[$couponIndex],
                'lines' => $lines,
            ];
        }
    }

    /** ループ内は new ゼロ：ミュータブル小計に積算し、割引・送料は int で受ける */
    public function benchCheckout(): void
    {
        $sink = 0;
        $subtotal = $this->subtotal;

        foreach ($this->carts as $entry) {
            $subtotal->set(0);
            $weight = 0;
            foreach ($entry['lines'] as $line) {
                $subtotal->add($line->subtotal());
                $weight += $line->weight();
            }

            $discount = $entry['coupon']->discountFor($subtotal->amount()); // 多態 dispatch（int）
            $subtotal->subtract($discount);
            $discounted = $subtotal->amount();

            if ($discounted >= self::FREE_THRESHOLD) {
                $shipping = 0;
            } else {
                $shipping = self::FALLBACK_FEE;
                foreach ($this->rates->ratesFor($entry['region']) as $rate) {
                    if ($weight <= $rate->maxWeight) {
                        $shipping = $rate->fee->amount(); // getter＝生成なし
                        break;
                    }
                }
            }

            $sink += $discounted + $shipping;
        }

        $this->sink = $sink;
    }
}

/**
 * ミュータブルな小計 accumulator。ループ内で 1 インスタンスを使い回し、
 * カートごとに set(0) でリセットする（Money の再生成を避けるための土台）。
 */
final class MutableSubtotal
{
    public function __construct(private int $amount = 0) {}

    public function set(int $amount): void
    {
        $this->amount = $amount;
    }

    public function add(int $amount): void
    {
        $this->amount += $amount;
    }

    public function subtract(int $amount): void
    {
        $this->amount -= $amount;
    }

    public function amount(): int
    {
        return $this->amount;
    }
}

/** int で完結する明細（Money を持たない＝生成コストなし）。 */
final class LeanCartLine
{
    public function __construct(
        private readonly int $unitPrice,
        private readonly int $qty,
        private readonly int $unitWeight,
    ) {}

    public function subtotal(): int
    {
        return $this->unitPrice * $this->qty;
    }

    public function weight(): int
    {
        return $this->unitWeight * $this->qty;
    }
}

/**
 * int を返すクーポン（Money を作らない）。多態 dispatch は維持するので、
 * JIT のインライン化余地は残しつつ、割引計算のアロケーションだけを消す。
 */
interface LeanCoupon
{
    public function discountFor(int $subtotal): int;
}

final class LeanFixedCoupon implements LeanCoupon
{
    public function __construct(private readonly int $amount) {}

    public function discountFor(int $subtotal): int
    {
        return min($this->amount, $subtotal);
    }
}

final class LeanRateCoupon implements LeanCoupon
{
    public function __construct(private readonly int $percent) {}

    public function discountFor(int $subtotal): int
    {
        return intdiv($subtotal * $this->percent, 100);
    }
}

final class LeanThresholdCoupon implements LeanCoupon
{
    public function __construct(
        private readonly int $threshold,
        private readonly int $amount,
    ) {}

    public function discountFor(int $subtotal): int
    {
        return $subtotal >= $this->threshold ? $this->amount : 0;
    }
}
