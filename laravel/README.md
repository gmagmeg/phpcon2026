# performance-php

PHP 8.5 の **OPcache / JIT** を「作って・測って・確かめる」ための検証環境。

## 構成

- **PHP 8.5** (PHP-FPM, Debian bookworm / glibc ※計測精度のため Alpine は不使用)
- **Laravel 13**
- **nginx 1.27** (Webサーバー)
- DB なし（session/cache=file, queue=sync）

配信モデルは **クラシック（リクエスト毎）**。Octane のような常駐ワーカーは使わないため、
OPcache が「コンパイル結果をリクエスト跨ぎでキャッシュする」効果を素直に観測できる。

## 起動

```sh
docker compose up -d --build
```

- アプリ: http://localhost:8001/
- OPcache/JIT 状態(JSON): http://localhost:8001/perf/status
- phpinfo: http://localhost:8001/perf/phpinfo

ポートは `APP_PORT`（既定 8001 / reveal.js が 8000 を使うため）で変更可。

## OPcache / JIT 設定の切替（リビルド不要）

`compose.yaml` の環境変数で切り替える。`docker compose up -d` で起動時に
`conf.d/zzz-perf.ini` が再生成される。

| 変数 | 既定 | 説明 |
|------|------|------|
| `OPCACHE_ENABLE` | `1` | OPcache 有効/無効 |
| `OPCACHE_ENABLE_CLI` | `1` | CLI での OPcache 有効/無効 |
| `OPCACHE_VALIDATE_TIMESTAMPS` | `1` | ファイル更新チェック（本番想定なら `0`） |
| `OPCACHE_JIT` | `tracing` | `tracing` / `function` / `disable` / 数値CRTO（例 `1255`） |
| `OPCACHE_JIT_BUFFER_SIZE` | `128M` | `0` で JIT 無効 |
| `OPCACHE_MEMORY` | `256` | OPcache メモリ(MB) |

例: OPcache を無効にして比較
```sh
OPCACHE_ENABLE=0 docker compose up -d
curl -s http://localhost:8001/perf/status | jq .opcache.enabled
```

例: JIT を無効にして比較
```sh
OPCACHE_JIT_BUFFER_SIZE=0 docker compose up -d
```

## CLI ベンチ（JIT 検証の定番）

```sh
# 条件を変えて一括実行（OPcache OFF / JIT OFF / tracing / function）
docker compose exec app sh bench/run.sh bench/math_jit.php

# 個別に条件指定（php -d で上書き）
docker compose exec app php -d opcache.jit=tracing -d opcache.jit_buffer_size=64M bench/math_jit.php  # JIT ON
docker compose exec app php -d opcache.jit_buffer_size=0 bench/math_jit.php                            # JIT OFF
```

サンプル:
- `bench/math_jit.php` … 数値計算ループ（JIT が効きやすい典型）
- `bench/in_array_vs_isset.php` … 計算量で選ぶ視点（in_array O(n) vs isset O(1)）
- `bench/lib.php` … 計測ヘルパ

参考実測（数値計算ループ, 同一マシン）:
```
JIT ON  (tracing) :  ~82 ms
JIT OFF           : ~359 ms   → 約4.4倍
```

## 停止

```sh
docker compose down
```
