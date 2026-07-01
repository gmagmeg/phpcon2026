<?php

declare(strict_types=1);

namespace App\Benchmarks;

final class TransferRequestFixtures
{
    /**
     * @return list<TransferRequest>
     */
    public static function many(int $count, int $payeeCount = 50): array
    {
        $requests = [];
        $ts = 1_700_000_000;

        for ($i = 0; $i < $count; $i++) {
            $amount = 100 + ($i % 900);
            $payeeIndex = $i % $payeeCount;
            $payeeId = 'user' . $payeeIndex;
            $payeeName = 'Wallet ' . $payeeIndex;
            $requestedBy = 'batch-job-' . ($i % 5);

            $requests[] = new TransferRequest(
                new AccountId('merchant'),
                new ParticipantName('Merchant Pool'),
                new AccountId($payeeId),
                new ParticipantName($payeeName),
                new ParticipantName($requestedBy),
                new Money($amount),
                new OccurredAt($ts + $i * 60),
                new IdempotencyKey('transfer-' . $i),
            );
        }

        return $requests;
    }

    public static function single(): TransferRequest
    {
        return new TransferRequest(
            new AccountId('merchant'),
            new ParticipantName('Merchant Pool'),
            new AccountId('user42'),
            new ParticipantName('Wallet 42'),
            new ParticipantName('batch-job-4'),
            new Money(740),
            new OccurredAt(1_700_000_000),
            new IdempotencyKey('transfer-42'),
        );
    }

    /**
     * @return list<bool>
     */
    public static function randomBits(int $count): array
    {
        $bits = [];
        $seed = 123456789;

        for ($i = 0; $i < $count; $i++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $bits[] = (bool) ($seed & 1);
        }

        return $bits;
    }
}
