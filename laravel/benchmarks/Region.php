<?php

declare(strict_types=1);

namespace App\Benchmarks;

/** 配送地域。送料テーブルのキーになる型付き列挙。 */
enum Region: string
{
    case North = 'north';
    case Central = 'central';
    case South = 'south';
}
