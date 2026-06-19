<?php

declare(strict_types=1);

namespace App\Benchmarks;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * 属性アクセスの中身を対比するためのベンチ用モデル（テーブル不要）。
 *
 *   total  : 計算アクセサ（price * qty ＝ 純 PHP 算術）→ JIT が効きやすい
 *   due_at : datetime キャスト（Carbon を構築 ＝ C 側 + オブジェクト生成）→ JIT 素通し
 *
 * どちらもスカラー／非キャッシュ経路なので、アクセスのたびに中身が再実行される。
 */
class BenchOrder extends Model
{
    protected $guarded = [];

    /** 計算アクセサ：純 PHP の整数算術 */
    protected function total(): Attribute
    {
        return Attribute::make(get: fn (): int => $this->price * $this->qty);
    }

    /** datetime キャスト：Carbon 構築（C 側の日付パース＋オブジェクト生成） */
    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }
}
