<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 3 段継承の中段。abstract（step A）を parent:: で呼び step B を積む。
 * DeepInheritanceScorer の親。
 */
class MidInheritanceScorer extends AbstractInheritanceScorer
{
    public function score(TransferRequest $request, int $iteration): int
    {
        $base = parent::score($request, $iteration);                     // step A
        return ($base + strlen($request->payerId->key())) & 0xffff;      // step B
    }
}
