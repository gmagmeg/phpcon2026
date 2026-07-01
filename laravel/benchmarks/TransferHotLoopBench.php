<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 同じ振替ドメインオブジェクトを使いつつ、
 * 1 件の要求をホットループで繰り返し評価して JIT 効果を見やすくする。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(300)]
#[Bench\Iterations(5)]
class TransferHotLoopBench
{
    private const N = 20000;

    /** @var array{payer_id:string,payee_id:string,requested_by:string,amount:int} */
    private array $row;

    private TransferRequest $request;

    private TransferFeePolicy $feePolicy;

    private int $sink = 0;

    public function setUp(): void
    {
        $this->row = [
            'payer_id' => 'merchant',
            'payee_id' => 'user42',
            'requested_by' => 'batch-job-4',
            'amount' => 740,
        ];

        $this->request = new TransferRequest(
            new AccountId('merchant'),
            new ParticipantName('Merchant Pool'),
            new AccountId('user42'),
            new ParticipantName('Wallet 42'),
            new ParticipantName('batch-job-4'),
            new Money(740),
            new OccurredAt(1_700_000_000),
            new IdempotencyKey('transfer-42'),
        );

        $this->feePolicy = new TransferFeePolicy(new Money(10), 20);
    }

    public function benchArray(): void
    {
        $row = $this->row;
        $score = 0;

        for ($i = 0; $i < self::N; $i++) {
            $amount = $row['amount'];
            $fee = max(10, intdiv($amount, 20));
            $gross = $amount + $fee;
            $multiplier = ($i & 7) + 1;
            $weight = strlen($row['payer_id']) + strlen($row['payee_id']) + strlen($row['requested_by']);
            $score += (($gross * $multiplier) ^ ($weight + $i)) & 0xffff;
        }

        $this->sink = $score;
    }

    public function benchTyped(): void
    {
        $request = $this->request;
        $feePolicy = $this->feePolicy;
        $score = 0;

        for ($i = 0; $i < self::N; $i++) {
            $amount = $request->amount->amount();
            $fee = $feePolicy->feeAmountFor($request);
            $gross = $amount + $fee;
            $multiplier = ($i & 7) + 1;
            $weight = strlen($request->payerId->key())
                + strlen($request->payeeId->key())
                + strlen($request->requestedBy->value);
            $score += (($gross * $multiplier) ^ ($weight + $i)) & 0xffff;
        }

        $this->sink = $score;
    }
}
