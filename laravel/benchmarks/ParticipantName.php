<?php

declare(strict_types=1);

namespace App\Benchmarks;

final readonly class ParticipantName
{
    public function __construct(public readonly string $value) {}
}
