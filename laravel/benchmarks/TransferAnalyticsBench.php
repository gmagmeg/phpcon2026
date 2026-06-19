<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 同じ振替ドメインオブジェクトを入力に使いながら、
 * イベント生成を排して計算・分岐・集計を主役にしたベンチ。
 *
 * TransferBench よりも new / 配列追加を減らし、JIT が効きやすい
 * 「ホットループ上の整数演算・小メソッド呼び出し」に寄せる。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(300)]
#[Bench\Iterations(5)]
class TransferAnalyticsBench
{
    private const N = 5000;
    private const PAYEE_COUNT = 50;
    private const PAYER_ID = 'merchant';
    private const PAYER_NAME = 'Merchant Pool';
    private const SCORE_STEPS = 24;

    /**
     * @var list<array{
     *   payer_id:string,
     *   payer_name:string,
     *   payee_id:string,
     *   payee_name:string,
     *   requested_by:string,
     *   amount:int,
     *   idempotency_key:string
     * }>
     */
    private array $rows;

    /** @var list<TransferRequest> */
    private array $typedRequests;

    private TransferFeePolicy $feePolicy;

    private int $sink = 0;

    public function setUp(): void
    {
        $this->rows = [];
        $this->typedRequests = [];
        $this->feePolicy = new TransferFeePolicy(new Money(10), 20);
        $ts = 1_700_000_000;

        for ($i = 0; $i < self::N; $i++) {
            $amount = 100 + ($i % 900);
            $payeeIndex = $i % self::PAYEE_COUNT;
            $payeeId = 'user' . $payeeIndex;
            $payeeName = 'Wallet ' . $payeeIndex;
            $requestedBy = 'batch-job-' . ($i % 5);
            $idempotencyKey = 'transfer-' . $i;

            $this->rows[] = [
                'payer_id' => self::PAYER_ID,
                'payer_name' => self::PAYER_NAME,
                'payee_id' => $payeeId,
                'payee_name' => $payeeName,
                'requested_by' => $requestedBy,
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
            ];

            $this->typedRequests[] = new TransferRequest(
                new AccountId(self::PAYER_ID),
                new ParticipantName(self::PAYER_NAME),
                new AccountId($payeeId),
                new ParticipantName($payeeName),
                new ParticipantName($requestedBy),
                new Money($amount),
                new OccurredAt($ts + $i * 60),
                new IdempotencyKey($idempotencyKey),
            );
        }
    }

    public function benchArray(): void
    {
        $grossTotal = 0;
        $netTotal = 0;
        $feeTotal = 0;
        $riskScore = 0;
        $checksum = 0;

        foreach ($this->rows as $row) {
            $amount = $row['amount'];
            $fee = max(10, intdiv($amount, 20));
            $gross = $amount + $fee;
            $net = $amount - $fee;
            $multiplier = ($amount & 7) + 1;
            $labelWeight = strlen($row['payer_id']) + strlen($row['payee_id']) + strlen($row['requested_by']);
            $weighted = ($gross * $multiplier) + ($labelWeight * 3);
            $rolling = $weighted;

            for ($step = 1; $step <= self::SCORE_STEPS; $step++) {
                $rolling = (($rolling + $fee) * $step) - ($net % ($step + 3));
                $rolling ^= ($gross + $labelWeight + $step);
                $rolling += ($amount & $step);
                $rolling &= 0x3fffffff;
            }

            if (($amount & 1) === 0) {
                $riskScore += $rolling;
            } else {
                $riskScore -= $rolling;
            }

            $grossTotal += $gross;
            $netTotal += $net;
            $feeTotal += $fee;
            $checksum += (($grossTotal ^ $netTotal ^ $feeTotal) & 0xff) + $multiplier;
        }

        $this->sink = $grossTotal + $netTotal + $feeTotal + $riskScore + $checksum;
    }

    public function benchTyped(): void
    {
        $grossTotal = 0;
        $netTotal = 0;
        $feeTotal = 0;
        $riskScore = 0;
        $checksum = 0;
        $feePolicy = $this->feePolicy;

        foreach ($this->typedRequests as $request) {
            $amount = $request->amount->amount();
            $fee = $feePolicy->feeAmountFor($request);
            $gross = $amount + $fee;
            $net = $amount - $fee;
            $multiplier = ($amount & 7) + 1;
            $labelWeight = strlen($request->payerId->key())
                + strlen($request->payeeId->key())
                + strlen($request->requestedBy->value);
            $weighted = ($gross * $multiplier) + ($labelWeight * 3);
            $rolling = $weighted;

            for ($step = 1; $step <= self::SCORE_STEPS; $step++) {
                $rolling = (($rolling + $fee) * $step) - ($net % ($step + 3));
                $rolling ^= ($gross + $labelWeight + $step);
                $rolling += ($amount & $step);
                $rolling &= 0x3fffffff;
            }

            if (($amount & 1) === 0) {
                $riskScore += $rolling;
            } else {
                $riskScore -= $rolling;
            }

            $grossTotal += $gross;
            $netTotal += $net;
            $feeTotal += $fee;
            $checksum += (($grossTotal ^ $netTotal ^ $feeTotal) & 0xff) + $multiplier;
        }

        $this->sink = $grossTotal + $netTotal + $feeTotal + $riskScore + $checksum;
    }
}
