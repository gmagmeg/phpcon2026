<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 1 段継承。abstract の score()（step A）を parent:: で呼び、
 * 残りの step B/C を子で積む。parent:: 呼び出しは 1 回。
 */
final class ShallowInheritanceScorer extends AbstractInheritanceScorer
{
    public function score(TransferRequest $request, int $iteration): int
    {
        $base = parent::score($request, $iteration);                   // step A
        $base = ($base + strlen($request->payerId->key())) & 0xffff;   // step B
        return ($base + strlen($request->payeeId->key())) & 0xffff;    // step C
    }
}
