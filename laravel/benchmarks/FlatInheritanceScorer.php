<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 継承なし。step A/B/C をすべて 1 メソッドにインラインで書いた基準実装。
 * ShallowInheritanceScorer / DeepInheritanceScorer と同じ値を返す。
 */
final class FlatInheritanceScorer
{
    public function __construct(private TransferFeePolicy $feePolicy) {}

    public function score(TransferRequest $request, int $iteration): int
    {
        $amount = $request->amount->amount();
        $fee = $this->feePolicy->feeAmountFor($request);

        $base = (($amount + $fee) * (($iteration & 7) + 1)) & 0xffff;      // step A
        $base = ($base + strlen($request->payerId->key())) & 0xffff;       // step B
        return ($base + strlen($request->payeeId->key())) & 0xffff;        // step C
    }
}
