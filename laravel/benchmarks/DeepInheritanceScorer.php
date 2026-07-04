<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 3 段継承の末端。Abstract → Mid → Deep の 3 階層。
 * 実行時は parent:: を 2 段たどる（Deep→Mid→Abstract）。
 */
final class DeepInheritanceScorer extends MidInheritanceScorer
{
    public function score(TransferRequest $request, int $iteration): int
    {
        $base = parent::score($request, $iteration);                    // step A + B
        return ($base + strlen($request->payeeId->key())) & 0xffff;     // step C
    }
}
