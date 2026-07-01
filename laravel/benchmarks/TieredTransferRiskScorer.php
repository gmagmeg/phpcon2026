<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class TieredTransferRiskScorer implements TransferRiskScorer
{
    public function __construct(private TransferFeePolicy $feePolicy) {}

    public function score(TransferRequest $request, int $iteration): int
    {
        $amount = $request->amount->amount();
        $fee = $this->feePolicy->feeAmountFor($request);
        $requestedByWeight = strlen($request->requestedBy->value);
        $tier = ($amount >= 500 ? 3 : 1) + (($iteration >> 2) & 1);

        return (($amount - $fee + $requestedByWeight) * $tier) & 0xffff;
    }
}
