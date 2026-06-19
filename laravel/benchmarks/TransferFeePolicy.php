<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class TransferFeePolicy
{
    public function __construct(
        private Money $minimumFee,
        private int $rateDivisor,
    ) {}

    public function feeFor(TransferRequest $request): Money
    {
        return new Money($this->feeAmountFor($request));
    }

    public function feeAmountFor(TransferRequest $request): int
    {
        $proportional = intdiv($request->amount->amount(), $this->rateDivisor);

        return max($this->minimumFee->amount(), $proportional);
    }
}
