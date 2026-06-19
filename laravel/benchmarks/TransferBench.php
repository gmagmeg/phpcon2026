<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 実践的な題材：振替要求の受付から投影まで、JIT は効くか。
 *
 * 振替要求を 1 件ずつ処理し、次の業務フローを通す:
 *   1. 冪等性キーで重複判定
 *   2. 手数料を計算
 *   3. 出金口座・手数料回収口座・入金口座を更新
 *   4. 口座イベントを履歴に記録
 *   5. ステートメント投影を更新
 *
 * 配列版は同じ業務ルールを手続き的に実装し、型付き版は
 * TransferRequest / AccountRegistry / TransferService / AccountStatementBook
 * などのオブジェクト群に分解して比較する。
 *
 *   # JIT ON（tracing）
 *   vendor/bin/phpbench run benchmarks/TransferBench.php --report=aggregate \
 *     --php-config='opcache.enable_cli: 1' \
 *     --php-config='opcache.jit_buffer_size: 64M' \
 *     --php-config='opcache.jit: tracing'
 *   # JIT OFF
 *   vendor/bin/phpbench run benchmarks/TransferBench.php --report=aggregate \
 *     --php-config='opcache.enable_cli: 1' \
 *     --php-config='opcache.jit_buffer_size: 0'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
class TransferBench
{
    private const N = 5000;
    private const PAYEE_COUNT = 50;
    private const PAYER_ID = 'merchant';
    private const PAYER_NAME = 'Merchant Pool';
    private const FEE_COLLECTOR_ID = 'fee-vault';

    /**
     * @var list<array{
     *   datetime:string,
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

    /** DCE 防止 */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->rows = [];
        $this->typedRequests = [];
        $ts = 1_700_000_000;

        for ($i = 0; $i < self::N; $i++) {
            $timestamp = $ts + $i * 60;
            $amount = 100 + ($i % 900);
            $payeeIndex = $i % self::PAYEE_COUNT;
            $payeeId = 'user' . $payeeIndex;
            $payeeName = 'Wallet ' . $payeeIndex;
            $requestedBy = 'batch-job-' . ($i % 5);
            $keyIndex = $i > 0 && $i % 250 === 0 ? $i - 1 : $i;
            $idempotencyKey = 'transfer-' . $keyIndex;

            $this->rows[] = [
                'datetime' => date('Y-m-d H:i:s', $timestamp),
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
                new OccurredAt($timestamp),
                new IdempotencyKey($idempotencyKey),
            );
        }
    }

    /** 連想配列で業務フローを処理する版 */
    public function benchArray(): void
    {
        $accounts = $this->createArrayAccounts();
        $processed = [];
        $statements = [];
        $completed = 0;
        $duplicates = 0;
        $rejected = 0;

        foreach ($this->rows as $row) {
            $key = $row['idempotency_key'];
            if (isset($processed[$key])) {
                $duplicates++;
                continue;
            }

            $amount = $row['amount'];
            $fee = max(10, intdiv($amount, 20));
            $totalDebit = $amount + $fee;
            $payerId = $row['payer_id'];
            $payeeId = $row['payee_id'];

            if ($amount <= 0 || $payerId === $payeeId || $accounts[$payerId]['balance'] < $totalDebit) {
                $rejected++;
                continue;
            }

            $accounts[$payerId]['balance'] -= $amount;
            $withdrawEvent = [
                'account_id' => $payerId,
                'datetime' => $row['datetime'],
                'reference' => $key,
                'operation' => 'withdraw',
                'amount' => $amount,
                'name' => $row['payee_name'],
                'balance' => $accounts[$payerId]['balance'],
            ];
            $accounts[$payerId]['history'][] = $withdrawEvent;
            $this->applyArrayStatement($statements, $withdrawEvent);

            $accounts[$payerId]['balance'] -= $fee;
            $feeChargeEvent = [
                'account_id' => $payerId,
                'datetime' => $row['datetime'],
                'reference' => $key,
                'operation' => 'fee_charged',
                'amount' => $fee,
                'name' => $row['requested_by'],
                'balance' => $accounts[$payerId]['balance'],
            ];
            $accounts[$payerId]['history'][] = $feeChargeEvent;
            $this->applyArrayStatement($statements, $feeChargeEvent);

            $accounts[self::FEE_COLLECTOR_ID]['balance'] += $fee;
            $feeDepositEvent = [
                'account_id' => self::FEE_COLLECTOR_ID,
                'datetime' => $row['datetime'],
                'reference' => $key,
                'operation' => 'deposit',
                'amount' => $fee,
                'name' => $row['requested_by'],
                'balance' => $accounts[self::FEE_COLLECTOR_ID]['balance'],
            ];
            $accounts[self::FEE_COLLECTOR_ID]['history'][] = $feeDepositEvent;
            $this->applyArrayStatement($statements, $feeDepositEvent);

            $accounts[$payeeId]['balance'] += $amount;
            $depositEvent = [
                'account_id' => $payeeId,
                'datetime' => $row['datetime'],
                'reference' => $key,
                'operation' => 'deposit',
                'amount' => $amount,
                'name' => $row['payer_name'],
                'balance' => $accounts[$payeeId]['balance'],
            ];
            $accounts[$payeeId]['history'][] = $depositEvent;
            $this->applyArrayStatement($statements, $depositEvent);

            $processed[$key] = true;
            $completed++;
        }

        $this->sink = $this->arrayChecksum($accounts, $statements, $processed, $completed, $duplicates, $rejected);
    }

    /** 型付き VO とサービス群で業務フローを処理する版 */
    public function benchTyped(): void
    {
        $registry = $this->createTypedRegistry();
        $processed = new ProcessedTransferIndex();
        $statements = new AccountStatementBook();
        $service = new TransferService(
            new TransferFeePolicy(new Money(10), 20),
            new TransferValidator(),
            new AccountId(self::FEE_COLLECTOR_ID),
        );
        $completed = 0;
        $duplicates = 0;
        $rejected = 0;

        foreach ($this->typedRequests as $request) {
            $receipt = $service->process($request, $registry, $processed);
            $statements->applyReceipt($receipt);

            switch ($receipt->status) {
                case TransferStatus::Completed:
                    $completed++;
                    break;

                case TransferStatus::DuplicateIgnored:
                    $duplicates++;
                    break;

                case TransferStatus::Rejected:
                    $rejected++;
                    break;
            }
        }

        $this->sink = $registry->totalBalance()
            + $registry->totalHistoryCount()
            + $statements->checksum()
            + $processed->count()
            + $completed
            + $duplicates
            + $rejected;
    }

    /**
     * @return array<string, array{balance:int, history:list<array{account_id:string,datetime:string,reference:string,operation:string,amount:int,name:string,balance:int}>}>
     */
    private function createArrayAccounts(): array
    {
        $accounts = [
            self::PAYER_ID => ['balance' => 1_000_000_000, 'history' => []],
            self::FEE_COLLECTOR_ID => ['balance' => 0, 'history' => []],
        ];

        for ($i = 0; $i < self::PAYEE_COUNT; $i++) {
            $accounts['user' . $i] = ['balance' => 0, 'history' => []];
        }

        return $accounts;
    }

    private function createTypedRegistry(): AccountRegistry
    {
        $accounts = [
            new Account(new AccountId(self::PAYER_ID), 1_000_000_000),
            new Account(new AccountId(self::FEE_COLLECTOR_ID), 0),
        ];

        for ($i = 0; $i < self::PAYEE_COUNT; $i++) {
            $accounts[] = new Account(new AccountId('user' . $i), 0);
        }

        return new AccountRegistry($accounts);
    }

    /**
     * @param array<string, array{debit:int,credit:int,fee:int,event_count:int,ending_balance:int}> $statements
     * @param array{account_id:string,datetime:string,reference:string,operation:string,amount:int,name:string,balance:int} $event
     */
    private function applyArrayStatement(array &$statements, array $event): void
    {
        $accountId = $event['account_id'];

        if (! isset($statements[$accountId])) {
            $statements[$accountId] = [
                'debit' => 0,
                'credit' => 0,
                'fee' => 0,
                'event_count' => 0,
                'ending_balance' => 0,
            ];
        }

        switch ($event['operation']) {
            case 'withdraw':
                $statements[$accountId]['debit'] += $event['amount'];
                break;

            case 'deposit':
                $statements[$accountId]['credit'] += $event['amount'];
                break;

            case 'fee_charged':
                $statements[$accountId]['debit'] += $event['amount'];
                $statements[$accountId]['fee'] += $event['amount'];
                break;
        }

        $statements[$accountId]['event_count']++;
        $statements[$accountId]['ending_balance'] = $event['balance'];
    }

    /**
     * @param array<string, array{balance:int, history:list<array{account_id:string,datetime:string,reference:string,operation:string,amount:int,name:string,balance:int}>}> $accounts
     * @param array<string, array{debit:int,credit:int,fee:int,event_count:int,ending_balance:int}> $statements
     * @param array<string, true> $processed
     */
    private function arrayChecksum(
        array $accounts,
        array $statements,
        array $processed,
        int $completed,
        int $duplicates,
        int $rejected,
    ): int {
        $checksum = $completed + $duplicates + $rejected + count($processed);

        foreach ($accounts as $accountId => $account) {
            $checksum += $account['balance'] + count($account['history']) + strlen($accountId);
        }

        foreach ($statements as $accountId => $statement) {
            $checksum += $statement['debit']
                + $statement['credit']
                + $statement['fee']
                + $statement['event_count']
                + $statement['ending_balance']
                + strlen($accountId);
        }

        return $checksum;
    }
}
