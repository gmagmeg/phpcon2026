# JIT と CPU アーキテクチャの関係メモ

PHP の JIT ベンチを通じて分かった「JIT の効きは CPU / 実行形態に依存する」という知見の記録。

## 要点

- PHP の JIT は **実行時にネイティブ機械語を生成** する。生成先はアーキごとに別バックエンド（**x86-64** / **aarch64**）で、aarch64 対応は x86-64 より新しい。
- したがって同じ PHP コードでも、**どの CPU で**、かつ **ネイティブ実行か翻訳（エミュ）実行か** で JIT の効き幅が変わる。
- コンテナは CPU をエミュレートしない。ホスト CPU を直接共有する。ISA が食い違うと Docker が翻訳層（Rosetta/QEMU）を挟む＝ここが遅さと歪みの原因。

## 計測環境

- ホスト: **Apple M4（arm64）** / macOS
- コンテナ: Docker Desktop, PHP 8.5.7, OPcache/JIT 有効
- ツール: phpbench（`laravel/benchmarks/`）

## 計測方法の落とし穴（最重要）

phpbench の `--php-config` は **JSON オブジェクト** を要求する。

- 誤: `--php-config='opcache.jit_buffer_size: 0'` … bare `key: value` は **無視され**、Docker 既定（`jit=tracing`/128M）で走る。→ OFF / function / tracing が全部 tracing になり「JIT が効かない（横ばい）」という誤結論を生む。
- 正: `--php-config='{"opcache.enable_cli": 1, "opcache.jit_buffer_size": 0}'`
- 検証: 計測サブプロセス内で `opcache_get_status()['jit']['on']` が **OFF→false / tracing→true** に切り替わることを必ず確認する。

## ARM64 ネイティブの結果

Cart（クーポン割引・送料計算 / 100 revs）:

| 実装 | JIT OFF | function | tracing | OFF→tracing |
|---|---|---|---|---|
| 配列（手続き） | 1.60ms | 1.03ms | 0.94ms | 約1.70倍速 |
| VO（型付き） | 8.96ms | 7.44ms | 6.53ms | 約1.37倍速 |
| VO（低alloc・ループ内 new ゼロ） | 2.92ms | 1.95ms | 1.43ms | 約2.04倍速 |

TransferBench（振替要求フロー / 100 revs）:

| 実装 | JIT OFF | function | tracing | OFF→tracing |
|---|---|---|---|---|
| 配列 | 7.42ms | 5.63ms | 4.97ms | 約1.49倍速 |
| VO | 18.16ms | 16.29ms | 14.16ms | 約1.28倍速 |

## わかったこと（JIT の効きどころ）

- **JIT はバイトコード（int 演算・ループ）に効く。** 純粋な整数ループは tracing で大きく高速化（別計測で約9倍）。
- **オブジェクト生成（`new`）など C 側の処理は JIT を素通りする。** VO はこの alloc（Money の再生成）が支配的で、JIT の効きが薄い。
- **alloc を削るほど JIT が効く。** Cart の VO をループ内 `new` ゼロ（ミュータブル accumulator ＋ int 返し）にすると、効きが 1.37倍→2.04倍に拡大し、tracing で配列に迫った。
- datetime / Carbon キャストが JIT 後も遅いのと同じ構図：**「中身が C 側かバイトコードか」で効き幅が決まる。**

## x86-64 エミュレーション実験

M4（ARM）上で amd64 コンテナを立て、同じ Dockerfile・同じベンチを計測した。

実行の積み重なり（3層）:

```
① Apple M4（物理・ARM64）
② Docker Desktop の軽量 Linux VM（これも ARM64 = VirtualApple・ネイティブ仮想化で速い）
③ amd64 コンテナの x86-64 バイナリ → Rosetta 2 / QEMU が x86命令→ARM命令に翻訳（← 遅さ・歪みの原因）
```

emu x86-64 の結果（参考値・エミュレーション）:

| 題材 / 実装 | JIT OFF | tracing | OFF→tracing |
|---|---|---|---|
| Cart 配列 | 2.75ms | 1.42ms※ | 約1.9倍 |
| Cart VO | 15.32ms | 9.48ms | 約1.62倍 |
| Cart VO(低alloc) | 4.73ms | 1.79ms | 約2.65倍 |
| Transfer 配列 | 11.34ms | 7.74ms | 約1.46倍 |
| Transfer VO | 29.99ms | 20.46ms | 約1.47倍 |

※ノイズあり（配列 OFF の変動大）。計算結果は tracing でも 61416620 で正しく、エミュ下でも JIT は機能する。

観察:
- **絶対値は ARM64 ネイティブより遅い**（翻訳オーバーヘッド、約1.5〜1.7倍）。実機 x86 の性能ではない。
- JIT 比（OFF→tracing）の歪みは **方向が予測不能**。今回はむしろ大きめに出た（OFF の解釈実行がエミュで特に重くなり、相対的に JIT の勝ちが大きく見える）。
- スライドの Transfer **VO tracing 3.847ms（4.66倍）は、ARM64・emu-x86 どちらでも再現しなかった**。

## ネイティブ vs 翻訳の考察

- 翻訳系（Rosetta/QEMU）の **最悪ケースが「実行時に生成される機械語」＝まさに JIT**。JIT が吐いた x86 機械語を、翻訳器がさらに ARM へ再翻訳するため、JIT の旨みが潰れる。
- ネイティブ x86 ではこの再翻訳が無い → **tracing 側が不均一に跳ねる余地** がある。よってスライドの VO tracing 値（3.847ms / 4.66倍）は、**翻訳層の無いネイティブ x86 でこそ再現する可能性**がある（仮説・未実測）。
- 「クラウド VM が違う」のは、物理 CPU が Intel/AMD になり翻訳層が消えて x86-64 が直で走るから。コンテナ構成は同じでも土台の CPU が変わる。

## 結論

- 「JIT が効くか」は **コード次第（バイトコード vs C 側 alloc）** だけでなく、**CPU アーキと実行形態（ネイティブ / 翻訳）にも依存** する。
- ベンチ値は必ず **(アーキ, ネイティブ/エミュ, JIT 設定, 計測ツール設定)** を明記して残す。数値だけを一人歩きさせない。
- x86 の「実力値」を出すには、翻訳の無いネイティブ x86 環境（実機 or x86-64 の VM / CI）が必須。GitHub Actions の `ubuntu-latest` はネイティブ x86-64 で無料枠が使える。
