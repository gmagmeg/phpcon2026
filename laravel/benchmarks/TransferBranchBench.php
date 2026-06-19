<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 分岐予測しやすいケースとしづらいケースを比較する。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class TransferBranchBench
{
    private const N = 20000;

    private TransferRequest $request;

    /** @var list<bool> */
    private array $predictableBits;

    /** @var list<bool> */
    private array $randomBits;

    private TransferFeePolicy $feePolicy;

    private int $sink = 0;

    public function setUp(): void
    {
        $this->request = TransferRequestFixtures::single();
        $this->predictableBits = [];
        for ($i = 0; $i < self::N; $i++) {
            $this->predictableBits[] = true;
        }
        $this->randomBits = TransferRequestFixtures::randomBits(self::N);
        $this->feePolicy = new TransferFeePolicy(new Money(10), 20);
    }

    public function benchPredictableBranch(): void
    {
        $score = 0;
        $request = $this->request;
        $feePolicy = $this->feePolicy;

        for ($i = 0; $i < self::N; $i++) {
            $amount = $request->amount->amount();
            $fee = $feePolicy->feeAmountFor($request);

            if ($this->predictableBits[$i]) {
                $score += ($amount + $fee) * 3;
            } else {
                $score -= ($amount - $fee) * 2;
            }
        }

        $this->sink = $score;
    }

    public function benchUnpredictableBranch(): void
    {
        $score = 0;
        $request = $this->request;
        $feePolicy = $this->feePolicy;

        for ($i = 0; $i < self::N; $i++) {
            $amount = $request->amount->amount();
            $fee = $feePolicy->feeAmountFor($request);

            if ($this->randomBits[$i]) {
                $score += ($amount + $fee) * 3;
            } else {
                $score -= ($amount - $fee) * 2;
            }
        }

        $this->sink = $score;
    }
}
