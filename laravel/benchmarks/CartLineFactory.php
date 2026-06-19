<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * ベンチ用の CartLine を決定的に生成するファクトリ。
 *
 * (cartIndex, lineIndex) をシードに単価・数量・重量を算出する。
 * 手続き版（CartCheckoutArrayBench）は同じ式を素の配列で持つので、
 * 両版の入力・結果が一致する。
 */
final class CartLineFactory
{
    public static function create(int $cartIndex, int $lineIndex): CartLine
    {
        $unitPrice = 300 + (($cartIndex + $lineIndex) % 20) * 100; // 300..2200
        $qty = 1 + (($cartIndex + $lineIndex) % 4);                // 1..4
        $unitWeight = 100 + (($cartIndex * $lineIndex) % 5) * 150; // 100..700

        return new CartLine(new Money($unitPrice), $qty, $unitWeight);
    }
}
