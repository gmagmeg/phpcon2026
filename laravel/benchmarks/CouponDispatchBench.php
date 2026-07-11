<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * クーポン割引シナリオ（年齢 / 会員 / 誕生日 の 3 種）を題材に、
 * 「継承ベース」と「インターフェースベース」のポリモーフィズムを比べる。
 *
 * 割引ルールは両者で完全に同一。違いは構造だけ:
 *   継承版           … 基底 LoyaltyCoupon のテンプレートメソッド経由。
 *                      共通処理（上限クランプ）が基底のメソッドとして残るため、
 *                      1 割引 = discountFor → calculate → clamp の 3 フレーム。
 *   インターフェース版 … DiscountCoupon 実装がフラットに完結。1 割引 = 1 フレーム。
 *
 * どちらも 3 型が混ざる呼び出し点（多型）なので virtual call は残る。
 * 読み筋: JIT は呼び出し自体を速くするが、フレームの段数は消せない
 * （InheritanceDispatchBench と同じ論調）。フラットな interface 版が有利のはず。
 *
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CouponDispatchBench.php --report=aggregate
 *   # JIT OFF で比較:
 *   docker compose exec app vendor/bin/phpbench run benchmarks/CouponDispatchBench.php --report=aggregate \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class CouponDispatchBench
{
    private const N = 5000;
    private const PURCHASE_MONTH = 6;

    /** @var list<CouponCustomer> */
    private array $customers;

    /** @var list<LoyaltyCoupon> */
    private array $loyaltyCoupons;

    /** @var list<DiscountCoupon> */
    private array $interfaceCoupons;

    /** DCE 防止: 結果をプロパティに残してループが消されないようにする */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->customers = [];
        for ($i = 0; $i < self::N; $i++) {
            $this->customers[] = new CouponCustomer(
                age: 18 + (($i * 7) % 60),
                memberRank: $i % 4,
                birthMonth: 1 + ($i % 12),
                subtotal: 500 + ($i % 90) * 100,
            );
        }

        $this->loyaltyCoupons = [
            new AgeLoyaltyCoupon(),
            new MemberLoyaltyCoupon(),
            new BirthdayLoyaltyCoupon(self::PURCHASE_MONTH),
        ];

        $this->interfaceCoupons = [
            new AgeCoupon(),
            new MemberCoupon(),
            new BirthdayCoupon(self::PURCHASE_MONTH),
        ];
    }

    /** 継承ベース: 基底のテンプレートメソッド経由で 3 種を多型ディスパッチ */
    public function benchInheritance(): void
    {
        $acc = 0;
        $i = 0;

        foreach ($this->customers as $customer) {
            $coupon = $this->loyaltyCoupons[$i % 3];
            $acc += $coupon->discountFor($customer, $customer->subtotal);
            $i++;
        }

        $this->sink = $acc;
    }

    /** インターフェースベース: フラットな実装を 3 種で多型ディスパッチ */
    public function benchInterface(): void
    {
        $acc = 0;
        $i = 0;

        foreach ($this->customers as $customer) {
            $coupon = $this->interfaceCoupons[$i % 3];
            $acc += $coupon->discountFor($customer, $customer->subtotal);
            $i++;
        }

        $this->sink = $acc;
    }
}
