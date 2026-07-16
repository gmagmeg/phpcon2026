<?php

declare(strict_types=1);

namespace App\Benchmarks;

use PhpBench\Attributes as Bench;

/**
 * if / switch / match に対する JIT の効果を比較するベンチマーク。
 *
 * 単純判定:
 * - setUp() で作った bool 配列を読み、計算を含めずに分岐する
 * - if は == / === を個別に計測する
 * - switch は緩い比較、match は厳密比較を行う
 *
 * 計算結果による判定:
 * - ループ内で整数を計算し、その結果を4分岐させる
 * - if / switch / match で計算式と各分岐の結果を揃える
 *
 * 分岐部分の推定値を出す場合は、同じ JIT 設定の未丸め値で差し引く。
 * - 単純判定: 各 bench*Boolean - benchBooleanBaseline
 * - 計算結果: 各 bench*Calculated - benchCalculatedBaseline
 *
 * # JIT OFF
 * docker compose exec app vendor/bin/phpbench run benchmarks/ConditionalBench.php --report=aggregate \
 *   --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "0"}'
 *
 * # JIT function
 * docker compose exec app vendor/bin/phpbench run benchmarks/ConditionalBench.php --report=aggregate \
 *   --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "function"}'
 *
 * # JIT tracing
 * docker compose exec app vendor/bin/phpbench run benchmarks/ConditionalBench.php --report=aggregate \
 *   --php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": "64M", "opcache.jit": "tracing"}'
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(2)]
#[Bench\Revs(200)]
#[Bench\Iterations(5)]
class ConditionalBench
{
    private const N = 10000;

    /** @var list<bool> */
    private array $booleanValues;

    /** @var list<int> 単純判定の分岐結果と同じ値を持つ基準ループ用。 */
    private array $booleanResults;  

    /** 分岐結果を残し、計測対象の処理を不要な計算にしない。 */
    private int $sink = 0;

    public function setUp(): void
    {
        $this->booleanValues = [];
        $this->booleanResults = [];
        for ($i = 0; $i < self::N; $i++) {
            $value = ($i & 1) === 0;
            $this->booleanValues[] = $value;
            $this->booleanResults[] = $value ? 1 : 2;
        }
    }

    /** if + 緩い比較（==）による単純な bool 判定。 */
    public function benchIfLooseBoolean(): void
    {
        $sum = 0;
        $values = $this->booleanValues;
        for ($i = 0; $i < self::N; $i++) {
            $value = $values[$i];
            if ($value == true) {
                $result = 1;
            } else {
                $result = 2;
            }
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** if + 厳密比較（===）による単純な bool 判定。 */
    public function benchIfStrictBoolean(): void
    {
        $sum = 0;
        $values = $this->booleanValues;
        for ($i = 0; $i < self::N; $i++) {
            $value = $values[$i];
            if ($value === true) {
                $result = 1;
            } else {
                $result = 2;
            }
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** switch による単純な bool 判定（case の比較は緩い比較）。 */
    public function benchSwitchBoolean(): void
    {
        $sum = 0;
        $values = $this->booleanValues;
        for ($i = 0; $i < self::N; $i++) {
            $value = $values[$i];
            switch ($value) {
                case true:
                    $result = 1;
                    break;
                case false:
                    $result = 2;
                    break;
            }
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** match による単純な bool 判定（厳密比較）。 */
    public function benchMatchBoolean(): void
    {
        $sum = 0;
        $values = $this->booleanValues;
        for ($i = 0; $i < self::N; $i++) {
            $value = $values[$i];
            $result = match ($value) {
                true => 1,
                false => 2,
            };
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** 単純判定用の基準ループ。配列参照と結果加算だけを残す。 */
    public function benchBooleanBaseline(): void
    {
        $sum = 0;
        $results = $this->booleanResults;
        for ($i = 0; $i < self::N; $i++) {
            $sum += $results[$i];
        }
        $this->sink = $sum;
    }

    /** if + 厳密比較による、計算結果の4分岐。 */
    public function benchIfCalculated(): void
    {
        $sum = 0;
        for ($i = 0; $i < self::N; $i++) {
            $value = (($i * 3) + 1) % 4;
            if ($value === 0) {
                $result = 10;
            } elseif ($value === 1) {
                $result = 20;
            } elseif ($value === 2) {
                $result = 30;
            } else {
                $result = 40;
            }
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** switch による、計算結果の4分岐。 */
    public function benchSwitchCalculated(): void
    {
        $sum = 0;
        for ($i = 0; $i < self::N; $i++) {
            $value = (($i * 3) + 1) % 4;
            switch ($value) {
                case 0:
                    $result = 10;
                    break;
                case 1:
                    $result = 20;
                    break;
                case 2:
                    $result = 30;
                    break;
                default:
                    $result = 40;
                    break;
            }
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** match による、計算結果の4分岐。 */
    public function benchMatchCalculated(): void
    {
        $sum = 0;
        for ($i = 0; $i < self::N; $i++) {
            $value = (($i * 3) + 1) % 4;
            $result = match ($value) {
                0 => 10,
                1 => 20,
                2 => 30,
                default => 40,
            };
            $sum += $result;
        }
        $this->sink = $sum;
    }

    /** 計算結果による判定用の基準ループ。計算式と結果加算だけを残す。 */
    public function benchCalculatedBaseline(): void
    {
        $sum = 0;
        for ($i = 0; $i < self::N; $i++) {
            $value = (($i * 3) + 1) % 4;
            $sum += $value;
        }
        $this->sink = $sum;
    }
}
