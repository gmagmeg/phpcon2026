<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class PromotionalTransferRiskScorer implements TransferRiskScorer
{
    public function __construct(private TransferFeePolicy $feePolicy) {}

    public function score(TransferRequest $request, int $iteration): int
    {
        $amount = $request->amount->amount();
        $fee = $this->feePolicy->feeAmountFor($request);
        $payeeWeight = strlen($request->payeeName->value);
        $promo = (($iteration & 3) + 1) * $payeeWeight;

        return (($amount * 2) + $promo - $fee) & 0xffff;
    }
}
