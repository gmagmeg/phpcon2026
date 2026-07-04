<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 同じ score() 計算を「継承なし / 1 段継承 / 3 段継承」で実装し、
 * parent:: の連鎖（継承の深さ）が JIT 下でコストになるかを確認する。
 * 3 実装はすべて同じ値を返す（step A/B/C の合成）。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(300)]
#[Bench\Iterations(5)]
class InheritanceDispatchBench
{
    private const N = 20000;

    private TransferRequest $request;

    private FlatInheritanceScorer $flat;

    private ShallowInheritanceScorer $shallow;

    private DeepInheritanceScorer $deep;

    private int $sink = 0;

    public function setUp(): void
    {
        $policy = new TransferFeePolicy(new Money(10), 20);
        $this->request = TransferRequestFixtures::single();
        $this->flat = new FlatInheritanceScorer($policy);
        $this->shallow = new ShallowInheritanceScorer($policy);
        $this->deep = new DeepInheritanceScorer($policy);
    }

    public function benchNoInheritance(): void
    {
        $score = 0;
        $request = $this->request;
        $scorer = $this->flat;

        for ($i = 0; $i < self::N; $i++) {
            $score += $scorer->score($request, $i);
        }

        $this->sink = $score;
    }

    public function benchShallowInheritance(): void
    {
        $score = 0;
        $request = $this->request;
        $scorer = $this->shallow;

        for ($i = 0; $i < self::N; $i++) {
            $score += $scorer->score($request, $i);
        }

        $this->sink = $score;
    }

    public function benchDeepInheritance(): void
    {
        $score = 0;
        $request = $this->request;
        $scorer = $this->deep;

        for ($i = 0; $i < self::N; $i++) {
            $score += $scorer->score($request, $i);
        }

        $this->sink = $score;
    }
}
