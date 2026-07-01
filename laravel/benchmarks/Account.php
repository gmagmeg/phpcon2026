<?php

declare(strict_types=1);

namespace App\Benchmarks;

/**
 * 口座。残高（型付き int）と、型付きイベントの履歴を持つ。
 * 出金・入金は残高を更新し、TransactionEvent を履歴に追記する。
 */
final class Account
{
    private int $balance;

    /** @var list<TransactionEvent> */
    private array $history = [];

    public function __construct(
        private readonly AccountId $id,
        int $opening,
    )
    {
        $this->balance = $opening;
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function canWithdraw(Money $amount): bool
    {
        return $this->balance >= $amount->amount();
    }

    public function withdraw(
        Money $amount,
        ParticipantName $name,
        OccurredAt $occurredAt,
        IdempotencyKey $reference,
    ): TransactionEvent {
        return $this->recordDebit($amount, $name, $occurredAt, $reference, TransactionType::Withdraw);
    }

    public function chargeFee(
        Money $amount,
        ParticipantName $name,
        OccurredAt $occurredAt,
        IdempotencyKey $reference,
    ): TransactionEvent {
        return $this->recordDebit($amount, $name, $occurredAt, $reference, TransactionType::FeeCharged);
    }

    public function deposit(
        Money $amount,
        ParticipantName $name,
        OccurredAt $occurredAt,
        IdempotencyKey $reference,
    ): TransactionEvent {
        $this->balance += $amount->amount();

        $event = new TransactionEvent(
            $this->id,
            $occurredAt,
            $reference,
            TransactionType::Deposit,
            $amount,
            $name,
            $this->balance,
        );
        $this->history[] = $event;

        return $event;
    }

    public function balance(): int
    {
        return $this->balance;
    }

    public function historyCount(): int
    {
        return count($this->history);
    }

    private function recordDebit(
        Money $amount,
        ParticipantName $name,
        OccurredAt $occurredAt,
        IdempotencyKey $reference,
        TransactionType $type,
    ): TransactionEvent {
        $this->balance -= $amount->amount();

        $event = new TransactionEvent(
            $this->id,
            $occurredAt,
            $reference,
            $type,
            $amount,
            $name,
            $this->balance,
        );
        $this->history[] = $event;

        return $event;
    }
}
