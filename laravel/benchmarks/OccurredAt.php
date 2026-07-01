<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class OccurredAt
{
    public function __construct(public readonly int $unixTime) {}
}
