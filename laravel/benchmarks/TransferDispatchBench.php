<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 呼び先が安定しているほど JIT が最適化しやすいかを確認する。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(300)]
#[Bench\Iterations(5)]
class TransferDispatchBench
{
    private const N = 20000;

    private TransferRequest $request;

    private FlatTransferRiskScorer $directScorer;

    private TransferRiskScorer $monomorphicScorer;

    /** @var list<TransferRiskScorer> */
    private array $polymorphicScorers;

    private int $sink = 0;

    public function setUp(): void
    {
        $policy = new TransferFeePolicy(new Money(10), 20);
        $this->request = TransferRequestFixtures::single();
        $this->directScorer = new FlatTransferRiskScorer($policy);
        $this->polymorphicScorers = [
            $this->directScorer,
            new TieredTransferRiskScorer($policy),
            new PromotionalTransferRiskScorer($policy),
        ];
        $this->monomorphicScorer = $this->directScorer;
    }

    public function benchDirectConcrete(): void
    {
        $score = 0;
        $request = $this->request;
        $scorer = $this->directScorer;

        for ($i = 0; $i < self::N; $i++) {
            $score += $scorer->score($request, $i);
        }

        $this->sink = $score;
    }

    public function benchMonomorphicInterface(): void
    {
        $score = 0;
        $request = $this->request;
        $scorer = $this->monomorphicScorer;

        for ($i = 0; $i < self::N; $i++) {
            $score += $scorer->score($request, $i);
        }

        $this->sink = $score;
    }

    public function benchPolymorphicInterface(): void
    {
        $score = 0;
        $request = $this->request;
        $scorers = $this->polymorphicScorers;
        $count = count($scorers);

        for ($i = 0; $i < self::N; $i++) {
            $score += $scorers[$i % $count]->score($request, $i);
        }

        $this->sink = $score;
    }
}
