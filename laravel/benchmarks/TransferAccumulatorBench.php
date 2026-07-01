<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * immutable な値オブジェクトの new コストと mutable 集約の差を確認する。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class TransferAccumulatorBench
{
    private const N = 5000;

    /** @var list<TransferRequest> */
    private array $requests;

    private TransferFeePolicy $feePolicy;

    private int $sink = 0;

    public function setUp(): void
    {
        $this->requests = TransferRequestFixtures::many(self::N);
        $this->feePolicy = new TransferFeePolicy(new Money(10), 20);
    }

    public function benchScalarAccumulator(): void
    {
        $total = 0;
        $feePolicy = $this->feePolicy;

        foreach ($this->requests as $request) {
            $total += $request->amount->amount() - $feePolicy->feeAmountFor($request);
        }

        $this->sink = $total;
    }

    public function benchImmutableMoney(): void
    {
        $total = Money::zero();
        $feePolicy = $this->feePolicy;

        foreach ($this->requests as $request) {
            $net = $request->amount->subtract($feePolicy->feeFor($request));
            $total = $total->add($net);
        }

        $this->sink = $total->amount();
    }

    public function benchMutableMoney(): void
    {
        $total = new MutableMoney(0);
        $feePolicy = $this->feePolicy;

        foreach ($this->requests as $request) {
            $total->addAmount($request->amount->amount());
            $total->subtractAmount($feePolicy->feeAmountFor($request));
        }

        $this->sink = $total->amount();
    }
}
