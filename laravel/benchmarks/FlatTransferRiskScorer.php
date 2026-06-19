<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class FlatTransferRiskScorer implements TransferRiskScorer
{
    public function __construct(private TransferFeePolicy $feePolicy) {}

    public function score(TransferRequest $request, int $iteration): int
    {
        $amount = $request->amount->amount();
        $fee = $this->feePolicy->feeAmountFor($request);
        $weight = strlen($request->payerId->key()) + strlen($request->payeeId->key());

        return (($amount + $fee) * (($iteration & 7) + 1) + $weight) & 0xffff;
    }
}
