<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 継承チェーンの根。step A（金額×係数）だけを担当し、
 * 追加の重み付けは子クラスが parent::score() の上に積む。
 */
abstract class AbstractInheritanceScorer
{
    public function __construct(protected TransferFeePolicy $feePolicy) {}

    public function score(TransferRequest $request, int $iteration): int
    {
        $amount = $request->amount->amount();
        $fee = $this->feePolicy->feeAmountFor($request);

        return (($amount + $fee) * (($iteration & 7) + 1)) & 0xffff;
    }
}
