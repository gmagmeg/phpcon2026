<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class TransferValidator
{
    public function canProcess(TransferRequest $request, Account $payer, Account $payee, Money $fee): bool
    {
        if ($request->payerId->key() === $request->payeeId->key()) {
            return false;
        }

        if (! $request->amount->isPositive()) {
            return false;
        }

        return $payer->canWithdraw($request->amount->add($fee)) && $payee->id()->key() === $request->payeeId->key();
    }
}
