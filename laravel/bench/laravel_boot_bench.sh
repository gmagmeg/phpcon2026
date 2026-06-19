#!/usr/bin/env sh
set -eu

# ---------------------------------------------------------------------------
# §2「OPcache が効くケース: Laravel」の計測ランナー。
#
# 同一の Laravel リクエスト処理（welcome / 約 500 ファイル）を、
#   OFF : OPcache 無効（毎プロセスで全ファイルを再コンパイル）
#   ON  : OPcache + file_cache（コンパイル済み opcode をプロセス跨ぎ再利用＝FPM 相当）
# の 2 条件で、それぞれ別プロセスとして N 回起動し、1 リクエストのブート時間を平均する。
#
# JIT はこの計測の対象外（コンパイルコストの比較）なので両条件とも無効に固定。
#
# 使い方（コンテナ内 / docker compose exec app sh bench/laravel_boot_bench.sh）:
#   sh bench/laravel_boot_bench.sh [RUNS] [MODE]   # 既定 30 回 / MODE=boot|request(既定)
# ---------------------------------------------------------------------------

RUNS="${1:-30}"
MODE="${2:-request}"
export MODE
SCRIPT="$(dirname "$0")/laravel_request.php"
FC_DIR="/tmp/opcache_laravel_bench"

# 共通: JIT は無効（OPcache のコンパイル効果だけを見る）
COMMON="-d opcache.jit_buffer_size=0 -d opcache.jit=disable"

# OFF: OPcache 完全無効
OFF_OPTS="-d opcache.enable=0 -d opcache.enable_cli=0"

# ON: OPcache 有効 + file_cache でプロセス跨ぎ再利用（FPM 相当）
#   validate_timestamps=0 で毎回の mtime チェックも省く（本番想定）。
ON_OPTS="-d opcache.enable=1 -d opcache.enable_cli=1 \
  -d opcache.validate_timestamps=0 \
  -d opcache.file_cache=${FC_DIR} \
  -d opcache.max_accelerated_files=20000 \
  -d opcache.memory_consumption=256"

# 平均を求めるヘルパ（awk で sum/count）
run_avg() {
  _opts="$1"
  _runs="$2"
  i=0
  while [ "$i" -lt "$_runs" ]; do
    # shellcheck disable=SC2086
    php $COMMON $_opts "$SCRIPT"
    i=$((i + 1))
  done | awk '{ s += $1; n++ } END { printf "%.2f", s / n }'
}

echo "[laravel-boot-bench] RUNS=${RUNS}  MODE=${MODE}  script=${SCRIPT}"
SHOW_FILES=1 php $COMMON $OFF_OPTS "$SCRIPT" >/dev/null
echo

# --- OFF ---
echo "--- OPcache OFF (warmup 3, measure ${RUNS}) ---"
run_avg "$OFF_OPTS" 3 >/dev/null
OFF_AVG="$(run_avg "$OFF_OPTS" "$RUNS")"
echo "OFF avg: ${OFF_AVG} ms"

# --- ON ---
echo "--- OPcache ON + file_cache (warmup 3, measure ${RUNS}) ---"
rm -rf "$FC_DIR"; mkdir -p "$FC_DIR"
run_avg "$ON_OPTS" 3 >/dev/null   # file_cache を温める
ON_AVG="$(run_avg "$ON_OPTS" "$RUNS")"
echo "ON  avg: ${ON_AVG} ms"

# --- 比 ---
echo
awk -v off="$OFF_AVG" -v on="$ON_AVG" 'BEGIN {
  printf "RESULT  OFF=%.2f ms  ON=%.2f ms  speedup=%.2fx  saved=%.2f ms\n", off, on, off/on, off-on
}'
