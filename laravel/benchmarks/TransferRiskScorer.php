<?php

declare(strict_types=1);

namespace App\Benchmarks;

interface TransferRiskScorer
{
    public function score(TransferRequest $request, int $iteration): int;
}
