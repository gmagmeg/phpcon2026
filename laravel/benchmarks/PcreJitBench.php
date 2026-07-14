<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * 正規表現 preg_* の「もう一つの JIT」= PCRE JIT を検証。
 *
 * 同じ複雑なパターンを、長めの文字列に対して繰り返し実行することで、
 * パターンの解析・コンパイルではなく PCRE JIT で生成されたコードの実行時間を測る。
 * 一致、不一致、複数一致を混在させ、文字列全体を走査するケースも含めている。
 *
 * PCRE JIT だけの効果を比較する場合は、両方とも OPcache JIT を無効にすること。
 *
 *   PCRE JIT ON:
 *     --php-config='opcache.jit_buffer_size: 0' --php-config='pcre.jit: 1'
 *   PCRE JIT OFF:
 *     --php-config='opcache.jit_buffer_size: 0' --php-config='pcre.jit: 0'
 *
 * preg_replace_callback のコールバックは、一致文字列をそのまま返す最小限の処理にして、
 * PHP 側の処理が PCRE JIT の差を隠しにくいようにしている。
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(50)]
#[Bench\Iterations(5)]
class PcreJitBench
{
    private const SUBJECT_COUNT = 2000;

    /** @var list<string> */
    private array $strings;

    /** @var \Closure(array<int, string>): string */
    private \Closure $replacement;

    private int $sink = 0;

    // 分岐、量化子、文字クラスを組み合わせた、実運用に近い固定パターン。
    // すべて非キャプチャグループにして、PHP 側の配列生成コストを抑える。
    private string $pattern = <<<'REGEX'
~
(?:
    [A-Z0-9._%+-]+@[A-Z0-9.-]+\.(?:com|net|org|co\.jp)
  | \b\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])\b
  | \b[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\b
  | \b(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\.(?:25[0-5]|2[0-4]\d|1?\d?\d)){3}\b
  | https?://[A-Z0-9.-]+(?:/[A-Z0-9._:/?&=%+-]*)?
)
~ix
REGEX;

    public function setUp(): void
    {
        $this->strings = [];
        $noise = str_repeat('alpha123 beta456 gamma789 delta012 ', 6);

        for ($i = 0; $i < self::SUBJECT_COUNT; $i++) {
            $lastOctet = $i % 255;
            $payload = match ($i % 4) {
                0 => "contact=user{$i}@example.com date=2026-07-14 "
                    . 'uuid=123e4567-e89b-12d3-a456-426614174000 '
                    . "ip=192.168.10.{$lastOctet} url=https://example.com/archive/item-{$i}",
                1 => "url=http://sub.example.net/search?q=item-{$i}&page=2 "
                    . "date=2025-12-31 mail=bench.{$i}+jit@example.co.jp",
                // 正規表現に似ているが一致しないデータ。末尾までの走査を発生させる。
                2 => "user{$i} at example dot com date=2026/07/14 "
                    . 'uuid=123e4567_e89b_12d3_a456_426614174000 ip=999.999.999.999',
                3 => "mail=perf{$i}@example.org ip=10.20.30.40 "
                    . 'uuid=550e8400-e29b-41d4-a716-446655440000',
            };

            $this->strings[] = $noise . $payload . ' ' . $noise;
        }

        $this->replacement = static fn (array $matches): string => $matches[0];
    }

    public function benchPregMatch(): void
    {
        $matched = 0;
        foreach ($this->strings as $subject) {
            $matched += \preg_match($this->pattern, $subject);
        }

        $this->sink = $matched;
    }

    public function benchPregMatchAll(): void
    {
        $matched = 0;
        foreach ($this->strings as $subject) {
            $matched += \preg_match_all($this->pattern, $subject);
        }

        $this->sink = $matched;
    }

    public function benchPregReplaceCallback(): void
    {
        $totalLength = 0;
        foreach ($this->strings as $subject) {
            $result = \preg_replace_callback($this->pattern, $this->replacement, $subject);
            $totalLength += $result === null ? 0 : \strlen($result);
        }

        $this->sink = $totalLength;
    }
}
