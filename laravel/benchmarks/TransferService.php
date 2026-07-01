<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class TransferService
{
    public function __construct(
        private TransferFeePolicy $feePolicy,
        private TransferValidator $validator,
        private AccountId $feeCollectorId,
    ) {}

    public function process(
        TransferRequest $request,
        AccountRegistry $registry,
        ProcessedTransferIndex $processed,
    ): TransferReceipt {
        if ($processed->has($request->idempotencyKey)) {
            return new TransferReceipt($request, TransferStatus::DuplicateIgnored, Money::zero(), []);
        }

        $payer = $registry->require($request->payerId);
        $payee = $registry->require($request->payeeId);
        $feeCollector = $registry->require($this->feeCollectorId);
        $fee = $this->feePolicy->feeFor($request);

        if (! $this->validator->canProcess($request, $payer, $payee, $fee)) {
            return new TransferReceipt($request, TransferStatus::Rejected, Money::zero(), []);
        }

        $events = [];
        $events[] = $payer->withdraw(
            $request->amount,
            $request->payeeName,
            $request->requestedAt,
            $request->idempotencyKey,
        );
        $events[] = $payer->chargeFee(
            $fee,
            $request->requestedBy,
            $request->requestedAt,
            $request->idempotencyKey,
        );
        $events[] = $feeCollector->deposit(
            $fee,
            $request->requestedBy,
            $request->requestedAt,
            $request->idempotencyKey,
        );
        $events[] = $payee->deposit(
            $request->amount,
            $request->payerName,
            $request->requestedAt,
            $request->idempotencyKey,
        );

        $processed->remember($request->idempotencyKey);

        return new TransferReceipt($request, TransferStatus::Completed, $fee, $events);
    }
}
