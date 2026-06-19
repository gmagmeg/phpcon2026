# JIT と OPcache を「正しく」期待する ― PHP 高速化の効きどころ

> 元アウトライン: [outline.md](outline.md)
> 一行テーマ: **「JIT を入れれば速くなる」は半分しか正しくない。何が速くなり、何が速くならないのかを計測で見極める。**

---

## 1. イントロ / 前提合わせ（つかみ）

- 自己紹介は最小限。このトークのゴールを最初に宣言する:
  - JIT / OPcache がそれぞれ「どこに効くのか」を、雰囲気ではなく計測で理解する。
  - 「JIT 入れたのに速くならない」の正体を理解して帰ってもらう。
- PHP 実行の流れをざっくり図解:
  - `PHP ソース → 字句/構文解析 → OPCODE（中間表現）→ Zend VM が実行`
- **OPcache** とは:
  - コンパイル結果をメモリにキャッシュし、毎リクエストの「ソース→OPCODE」変換を省く仕組み。
  - = コンパイルコストの削減がメイン。実行そのものを速くするわけではない。
- **JIT** とは（PHP 8.0+）:
 OPCODE の実行部分を速くする
- Opechache、JIT入れるとどこまで速くなるのかを見ていく

---

## 2. OPcache が効くケース

OPcache は話がわかりやすい。
コード量（コンパイル対象の規模）にほぼ比例する

- 薄い自作スクリプト … コンパイルコストが元々小さい → **効果も薄い**。
- 大量のクラス / ファイルを読む … 省略が積み上がる → **大きい**。

#### 計測結果（実測 / Docker・PHP 8.5 / 1 リクエストのブート時間・30 回平均）

Laravel の初期Welcomeページを OPcache OFF と ONで別プロセス 30 回起動して比較
（計測コード: `bench/laravel_request.php` / `bench/laravel_boot_bench.sh`）。

| 構成 | 1 リクエストのブート | 比 |
|------|---------------------|----|
| OPcache OFF | 146.89 ms | 1.0×（基準） |
| OPcache ON | **71.89 ms** | **約 2.04× 速い**（約 75ms 短縮） |

コード量が多いフレームワークでは効果が大きい。

#### 逆効果パターン：薄いスクリプトの寄せ集め

関数 1 個だけの極小ファイルを N 個 require するだけの処理を、Laravel と同じ方式（OFF＝毎回再コンパイル / ON＝`file_cache` 再利用）で計測。

計測コードを簡略化すると、やっていることはこれだけ:

```php
// ① 関数1個だけの極小ファイルを N 個生成（thin_gen.php 相当）
for ($i = 0; $i < $n; $i++) {
    file_put_contents("thin_{$i}.php", "<?php function thin_fn_{$i}(\$x){ return \$x + {$i}; }");
}

// ② それを require するだけ＝「薄いスクリプトの寄せ集め」（thin_script.php 相当）
$t0 = hrtime(true);
for ($i = 0; $i < $n; $i++) {
    require "thin_{$i}.php";   // OFF: 毎回コンパイル / ON: file_cache から読むだけ
}
echo (hrtime(true) - $t0) / 1e6, " ms\n";
```

> 実際の計測コード（OFF/ON 切替・30 回平均・N スイープ込み）:
> [`bench/thin_gen.php`](laravel/bench/thin_gen.php) / [`bench/thin_script.php`](laravel/bench/thin_script.php) / [`bench/thin_bench.sh`](laravel/bench/thin_bench.sh)

| require ファイル数 | OPcache OFF | OPcache ON | 比 |
|------|------------:|-----------:|----|
| 1 個（＝薄いスクリプト） | 0.030 ms | 0.036 ms | 0.83×（**速くならない**） |
| 50 個 | 0.199 ms | 0.243 ms | 0.82× |
| 500 個 | 1.636 ms | 1.891 ms | 0.87× |

どの行も ON は速くならなず、むしろ僅かに遅い。
キャッシュから読み込むオーバーヘッドの方が、薄いコードをそのまま読むよりも優った結果に。

あまり実務では存在しないだろうけれど、気を付ける。

---

## 3. 計測の枠組み

- ツール: **PHPBench** を使用。`@Revs` / `@Iterations` の意味を一言。
- 比較軸: **JIT OFF vs JIT ON**、さらに **JIT mode（function vs tracing）**（`opcache.jit` で指定）。
- 原則: JIT が速くするのは **OPCODE の実行部分**。標準関数や IO の中身（ネイティブ C）は速くできない。**計算が OPCODE で表現される処理**を狙う。

### 3-1. `opcache.jit` の設定値（`CRTO`）と最大の落とし穴

- **デフォルトは実質 OFF**。PHP 8 以降 `opcache.jit` のデフォルトは `tracing` だが、**`opcache.jit_buffer_size` のデフォルトが `0`**。バッファが 0 だと mode を何にしても **JIT は動かない**。「8.0 にしたから JIT 効いてるはず」が外れる最大要因。
- CLI（PHPBench はこれ）は **`opcache.enable_cli=1`** が別途必要。FPM とは別 ini。
- 最低限の設定:

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=128M   ; ← これを明示しないと JIT は無効
```

- `opcache.jit` の値は **4 桁 `CRTO`** で、`tracing` / `function` はそのエイリアス。

| 桁 | 意味 | 主な値 |
|----|------|--------|
| **C** (CPU) | CPU 固有最適化 | 0=なし / 1=AVX |
| **R** (Register) | レジスタ割り付け | 0=なし / 1=ブロック局所 / 2=グローバル |
| **T** (Trigger) | **いつ compile するか** | 0=ロード時に全関数 / 1=初回実行 / 3=実行中のホット関数 / **5=トレーシング** |
| **O** (Optimization) | 最適化レベル | 1=最小 … 4=コールツリー / 5=手続き内解析 |

| 別名 | 数値 | T（トリガ） |
|------|------|-------------|
| `function` | `1205` | 0（関数単位で全 compile） |
| `tracing` | `1254` | 5（ホットな trace を compile） |
| `on` | =`tracing` | |
| `off` / `disable` | — | 無効 |

> 言い方: 「`function` の `0` と `tracing` の `5` ＝ **第 3 桁（トリガ）の違い**が本質。残りはほぼ同じ」。

### 3-2. function JIT と tracing JIT の違い

- **function JIT**（T=0）… 関数を **まるごと** 機械語化。境界は 1 関数単位で、ホットかどうかに関係なく対象。
- **tracing JIT**（T=5）… まず VM で実行しながらプロファイルし、**ホットな実行経路（trace）** だけを compile する。trace は **関数境界を越えて** 伸びるのが肝:
  - ループ内で繰り返し呼ばれるメソッドを **インライン化** してひと続きの機械語にできる。
  - 実際に観測した型（「ここは常に `int`」）で **特殊化** できる。
  - 実行されない分岐はそもそも compile しない。
- だから「ホットループ＋メソッド呼び出し」では **tracing が function を上回る**（§4-5 で実測）。§4-4 の「型付き値オブジェクトが効く」話も tracing 前提。
- 閾値設定: `opcache.jit_hot_loops` / `jit_hot_func` などで「何回でホットと見なすか」を制御。マイクロベンチで効果が出ないときはこことウォームアップを疑う。

#### 実務上のトレードオフ（どちらを選ぶか）

| 観点 | function (T=0) | tracing (T=5) |
|------|----------------|---------------|
| compile タイミング | ロード時に全関数を一括 | 実行を観測し、ホットな trace を後から |
| ウォームアップ | **不要**（最初から効く） | **必要**（ホット検出までは素の VM） |
| compile 範囲 / メモリ | 全関数 → buffer 消費が大きい | ホット経路のみ → 小さく済む |
| 最適化の質 | 関数単位どまり | 関数境界を越えてインライン化・実型で特殊化 |
| 得意 | 起動直後から効かせたい | ホットループ・長時間プロセス（FPM 常駐・ワーカー） |
| 不利 | ホットループの特殊化は弱い | **短命・単発処理は warmup を回収できない** |

> **損益分岐**: 長く回るホットパスがあるなら tracing（本トークの数値計算・ドメイン層はこちら）。一方、**短命 CLI や 1 回こっきりの処理**では tracing の warmup コストが回収できず、function や素の VM の方が相対的に有利になりうる。「常に tracing が最速」ではない。

---

## 4. JIT はどこで効くのか

### 4-1. ケース①: 四則演算 / bcmath

四則演算（＋、ー、＊、／）、ループ、文字連結など

**OPCODE そのもの**（`ADD` / `MUL` など）→ JIT がネイティブ命令に置き換えられる。
- 計測: **ネイティブ float 演算 vs bcmath**（PHPBench・mode 比較）。
  - float 演算は JIT ON で大きく改善する見込み（OPCODE が支配的）。
  - bcmath は内部 C 実装なので JIT の恩恵は限定的、という対比も示す。
- ここで「**計算が OPCODE で表現される処理ほど JIT が効く**」という原則を確立。

### 4-2. ケース②: コールバックを取る標準関数（`array_map` / `usort`）

- 標準関数でも、**コールバック部分はユーザー定義 = OPCODE が発生する**。
- したがって `array_map` / `usort` の「中身のループは C だが、各要素で呼ばれるクロージャは JIT 対象」。
- 計測: コールバックの処理が重いほど JIT の効果が見える、という観点で mode 比較。
- **教訓**: 標準関数でも「ユーザーコードを実行する隙間」があれば JIT は効く。

### 4-3. ケース③: 正規表現の「もう一つの JIT」= PCRE JIT

- PHP の JIT とは別系統の、**PCRE ライブラリ自身が持つ JIT**（`pcre.jit`）の存在を紹介。
- 正規表現エンジンが内部でパターンを機械語化する仕組み。
- 計測: PCRE JIT ON/OFF で比較（PHPBench・mode）。
- **メッセージ**: 「JIT」と一口に言っても複数あり、効くレイヤーが違う。混同しないこと。

### 4-4. ★実践: 型付き値オブジェクト演算は JIT で大きく改善

> 本トークの **実践的な核**。「ドメイン層 = 型付きの値オブジェクトで計算を表現する層」に JIT が効くことを計測で示す。

#### 題材: `Money` 値オブジェクト（型付き / 型なし）

型付き（PHP 8.1+ / `readonly` + 型宣言）:

```php
final class Money
{
    public function __construct(private readonly int $amount) {}

    public function add(Money $o): self   { return new self($this->amount + $o->amount); }
    public function multiply(int $f): self { return new self($this->amount * $f); }
    public function amount(): int          { return $this->amount; }
}
```

型なし（型宣言を外しただけの等価コード）:

```php
final class MoneyLoose
{
    private $amount;
    public function __construct($amount) { $this->amount = $amount; }

    public function add($o)      { return new self($this->amount + $o->amount); }
    public function multiply($f) { return new self($this->amount * $f); }
    public function amount()     { return $this->amount; }
}
```

#### 計測マトリクス（PHPBench / tracing JIT / `@Warmup` あり）

3 水準 × JIT OFF/ON:

| 水準 | 内容 | JIT ON での読み筋 |
|------|------|------------------|
| **A: 生スカラー演算** | `$sum = $sum + $a; $sum = $sum * 2;` をループ | 大きく改善（到達できる上限の目安） |
| **B: 型付き Money** | `add` / `multiply` をループ | **大きく改善し A に肉薄** |
| **C: 型なし MoneyLoose** | B と同等処理 | 改善は鈍い（型ガードが残る） |

#### 読み筋（このスライドで語ること）

- JIT OFF: B も C も A より明確に遅い（メソッド呼び出し・プロパティアクセス・オブジェクト生成のオーバーヘッド）。B と C の差は小さい。
- JIT ON:
  - A は大きく改善（OPCODE がそのままネイティブ命令に）。
  - **B（型付き）が大きく改善し A に肉薄** — 型が静的に分かるため、JIT がメソッドをインライン化し、プロパティを直接スロットアクセスし、`int` 演算として特殊化できる。
  - C（型なし）は改善が鈍い — 型不明ゆえにガード・汎用パスが残る。
- **結論: B と C の差（＝型を付けたかどうか）が JIT ON で開く。**

> 「型を付ける」という設計上の正しさ（型安全）が、そのまま JIT の効きに直結する。

#### 補足（誠実に / 質疑対策）

- イミュータブルな値オブジェクトは毎演算で `new` するため、**アロケーション / GC コストは JIT でも消えない**。A（スカラー）に完全一致はしない。「計算部分の差は詰まるが、生成コストは別物」と正直に言う。
- アロケーションを排した純粋比較として、**型付き vs 型なしプロパティを読んで加算するだけのホットループ**も用意すると、型の効果がさらにクリアに見える。
- 効くのは tracing JIT（`opcache.jit=tracing`）＋ホットループが前提。単発呼び出しでは出ない。

#### 代入の計測（イミュータブル ＝ コンストラクタ代入）

イミュータブルな値オブジェクトの代入は「コンストラクタでプロパティに 1 回入れる」に集約される。型付き `readonly` プロパティ vs 型なしプロパティで、生成（= コンストラクタ代入）コストを比較する。

```php
final class TypedPoint
{
    public function __construct(
        private readonly int $x,
        private readonly int $y,
    ) {}
}

final class LoosePoint
{
    private $x;
    private $y;
    public function __construct($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }
}
```

```php
/** @Revs(1000000) @Iterations(5) @Warmup(2) */
class ConstructBench
{
    public function benchTyped(): void { new TypedPoint(1, 2); }
    public function benchLoose(): void { new LoosePoint(1, 2); }
}
```

読み筋:

- JIT ON で `benchTyped` は **プロパティ代入時の型検証が省かれ**、`int` は refcount も不要なので素のストアになる。
- `benchLoose` は汎用代入パスが残る → **差は「型を付けたかどうか」**。コンストラクタ代入というイミュータブルの定石部分に効く。
- 注意: `new` のアロケーションは両者共通かつ JIT でも消えない。差は代入パスぶんに限られる（誇張しない）。

#### 比較演算子の計測（`==` vs `===`）

```php
class CompareBench
{
    private int $a = 12345;
    private int $b = 12345;
    private string $s = '12345';

    /** @Revs(10000000) @Iterations(5) @Warmup(2) */
    public function benchIdenticalIntInt(): void { $r = $this->a === $this->b; }  // D1
    public function benchEqualIntInt(): void     { $r = $this->a == $this->b; }   // D2
    public function benchEqualIntNumStr(): void  { $r = $this->a == $this->s; }   // D3 強制変換
    public function benchIdenticalIntStr(): void { $r = $this->a === $this->s; }  // D4 型不一致→false
}
```

| 水準 | 比較 | JIT ON での読み筋 |
|------|------|------------------|
| D1 | `int === int` | 1 命令の整数比較に特殊化 |
| D2 | `int == int` | **D1 と同じ命令に潰れ差が消える** |
| D3 | `int == 数値文字列` | **突出して遅い・JIT でも縮まらない**（強制変換は C 側） |
| D4 | `int === string` | 型不一致で即 false、安い |

読み筋:

- **結論: JIT は型が揃えば `==` / `===` の差を消す。差が残るのは coercion のときだけ。**「`===` が速い」は型が判明していれば無効化される。
- 注意: 値はプロパティ経由で渡す（リテラル直書きだと JIT に定数畳み込みされ、比較自体が消えうる）。
- `==` の正しさの落とし穴（`0 == "a"` 等）は **性能とは別軸**。混ぜない。

#### ドメイン層への接続（ここが山場への伏線）

- ドメイン層を「型付きの値オブジェクト＋計算」で構成すると、JIT の恩恵を最大化できる。
- 逆に、メソッドの中身が標準関数や IO の寄せ集めだと効かない（中身はネイティブ C で JIT の対象外）。
- **「クラスで包んだ計算」に効く**のであって「クラスだから効く」ではない、と釘を刺す。

### 4-5. ケース④: JIT mode 自体で結果が変わる（function vs tracing）

> 同じ「JIT ON」でも mode で差が出る。§3-2 の function / tracing の違いを実測で確かめる。
> 題材は **アロケーションを排した純粋比較**（§4-4 補足で予告したホットループ）。生成済みインスタンスのプロパティを、ホットループからメソッド経由で読んで加算するだけ。`new` / GC コストを取り除き、**JIT mode の差**と**型の差**だけを残す。

```php
final class TypedBox
{
    public function __construct(private readonly int $value) {}
    public function value(): int { return $this->value; }
}

final class LooseBox            // 型宣言を外しただけ
{
    private $value;
    public function __construct($value) { $this->value = $value; }
    public function value() { return $this->value; }
}
```

```php
/** @Revs(100) @Iterations(5) @Warmup(2) */
class HotLoopBench
{
    private const N = 10000;
    private TypedBox $typed;
    private LooseBox $loose;
    private int $raw = 7;

    public function setUp(): void { /* インスタンスは 1 回だけ生成 */ }

    public function benchScalar(): void   // A: プロパティ直読み（呼び出しなし）
    { $s = 0; $v = $this->raw;     for ($i=0;$i<self::N;$i++) $s += $v; }
    public function benchTyped(): void    // B: TypedBox::value() を毎回呼ぶ
    { $s = 0; $b = $this->typed;   for ($i=0;$i<self::N;$i++) $s += $b->value(); }
    public function benchLoose(): void    // C: LooseBox::value()
    { $s = 0; $b = $this->loose;   for ($i=0;$i<self::N;$i++) $s += $b->value(); }
}
```

#### 計測結果（実測 / Docker・PHP 8.5 / `mode`＝平均 1 回あたり）

`HotLoopBench`（N=10000, `@Revs(100)`）を 3 構成で実行:

| 水準 | JIT OFF | function (`1205`) | tracing (`1254`) | OFF→tracing |
|------|--------:|------------------:|-----------------:|:-----------:|
| A: 生スカラー       | 17.74μs | 4.91μs  | **3.77μs** | 約 4.7倍 |
| B: 型付き TypedBox  | 91.64μs | 49.66μs | **38.45μs** | 約 2.4倍 |
| C: 型なし LooseBox  | 92.62μs | 52.09μs | **38.04μs** | 約 2.4倍 |

参考: アロケーションを含む `MoneyBench`（型付き Money / N=1000, `@Revs(1000)`）は **OFF 231μs → function 175μs → tracing 158μs**。`new` のコストが残るため改善幅は小さい。

#### 読み筋

- **JIT OFF → function → tracing で単調に速くなる。** 同じ「JIT ON」でも **function → tracing でさらに 2〜3 割**縮む（例: B 49.7μs → 38.4μs）。「JIT ON にした」だけでは語れない。
- **tracing が function を上回る理由**: ホットループ内の `value()` を **trace にインライン化**できるから。function は関数単位 compile なので、呼び出し境界が trace のように溶けない。
- **誠実な注意（重要）**: このループでは **型付き B と 型なし C の差はほぼ消える**（tracing で 38.4μs vs 38.0μs）。tracing は **観測した実行時の型で特殊化**するため、`int` が流れていれば宣言の有無はほとんど効かない。**「型を付ければ JIT で速くなる」を単純化しすぎない**。型宣言が効くのは `ConstructBench` のような「代入時の型検証」パスで、steady-state の読み出しでは差が出にくい。
- A（17.7→3.8μs）と B/C（92→38μs）の差は **メソッド呼び出しそのもののコスト**。tracing でも完全には消えない（呼び出しフレームのガード分）。

> **メッセージ**: 「JIT を入れた／入れない」は粗すぎる。**mode（function/tracing）まで含めて測る**。そして mode を変えても、効くのは結局「OPCODE のホットな実行経路」であり、標準関数や生成コストは別。

---

## 5. まとめ ― レイヤーで考える高速化戦略

### 5-1. 役割分担の整理

| 仕組み | 効く対象 | 典型的な場所 |
|--------|----------|--------------|
| OPcache | コード量が多い処理（コンパイルコスト） | 外部依存・フレームワーク層 |
| JIT | OPCODE 計算が支配的な処理 | コアドメイン・数値計算層 |

### 5-2. アーキテクチャとの相乗効果（このトークの主張）

- フレームワークを使いつつ、**ユーザー手続きコード（ドメインロジック）が厚くなる**設計では、両方の恩恵を受けられる。
- **クリーンアーキテクチャ的な層構造**と相性が良い:
  - **外部依存の層（フレームワーク・I/O）** → **OPcache** が効く。
  - **コアドメインの層（純粋なロジック・計算）** → **JIT** が効く。
- つまり「関心の分離」をすると、高速化の効きどころも自然と分離される。

### 5-3. 持ち帰ってほしいこと

1. JIT は「OPCODE の実行」を速くするだけ。標準関数の中身は速くならない。
2. JIT が効くのは数値計算・コールバック・ドメインロジック。
3. OPcache はコード量に比例して効く（フレームワーク向き）。
4. 「JIT」には PCRE JIT など別系統もある。
5. レイヤー設計とキャッシュ/JIT の効きどころは対応している。

---

## 付録（時間があれば / 質疑用ストック）

- 各 JIT mode（`tracing` vs `function`）の違いと使い分け。
- `opcache.jit_buffer_size` などの実運用設定の勘所。
- 計測の落とし穴（ウォームアップ、`@Revs` 設定、CLI vs FPM での挙動差）。
- ベンチコードの公開先 / 再現方法。
