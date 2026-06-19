#!/usr/bin/env sh
set -eu

# ---------------------------------------------------------------------------
# §2「薄いスクリプトでは OPcache の効果が薄い」を実証するランナー（Laravel の対比）。
#
# 極小ファイル（関数 1 個）を N 個 require するだけの処理を、
#   OFF : OPcache 無効（毎プロセスで再コンパイル）
#   ON  : OPcache + file_cache（コンパイル済み opcode をプロセス跨ぎ再利用＝FPM 相当）
# の 2 条件で別プロセス N 回起動して平均。さらに FILES 数を振って
# 「効果はコード量（コンパイル総量）に比例」も示す。
#   N=1   … 薄いスクリプトそのもの → 効果ほぼゼロ
#   N=500 … Laravel 相当のファイル数 → 効果が出てくる
#
# JIT は対象外（コンパイルコストの比較）なので両条件とも無効に固定。
#
# 使い方（コンテナ内）:
#   docker compose exec app sh bench/thin_bench.sh [RUNS] ["N1 N2 ..."]
#   既定: RUNS=30 / SWEEP="1 50 500"
# ---------------------------------------------------------------------------

RUNS="${1:-30}"
SWEEP="${2:-1 50 500}"
HERE="$(dirname "$0")"
SCRIPT="$HERE/thin_script.php"
GEN="$HERE/thin_gen.php"

COMMON="-d opcache.jit_buffer_size=0 -d opcache.jit=disable"
OFF_OPTS="-d opcache.enable=0 -d opcache.enable_cli=0"

run_avg() {
  _opts="$1"
  _runs="$2"
  i=0
  while [ "$i" -lt "$_runs" ]; do
    # shellcheck disable=SC2086
    php $COMMON $_opts "$SCRIPT"
    i=$((i + 1))
  done | awk '{ s += $1; n++ } END { printf "%.3f", s / n }'
}

echo "[thin-bench] RUNS=${RUNS}  SWEEP=\"${SWEEP}\"  script=${SCRIPT}"
echo

for N in $SWEEP; do
  DIR="/tmp/thin_payload_${N}"
  FC="/tmp/opcache_thin_${N}"

  php "$GEN" "$N" "$DIR" >/dev/null 2>&1

  FILES="$N"; PAYLOAD_DIR="$DIR"
  export FILES PAYLOAD_DIR

  ON_OPTS="-d opcache.enable=1 -d opcache.enable_cli=1 \
    -d opcache.validate_timestamps=0 \
    -d opcache.file_cache=${FC} \
    -d opcache.max_accelerated_files=20000 \
    -d opcache.memory_consumption=256"

  # OFF: warmup 3 → 計測
  run_avg "$OFF_OPTS" 3 >/dev/null
  OFF="$(run_avg "$OFF_OPTS" "$RUNS")"

  # ON: file_cache を作り直して温める → 計測
  rm -rf "$FC"; mkdir -p "$FC"
  run_avg "$ON_OPTS" 3 >/dev/null
  ON="$(run_avg "$ON_OPTS" "$RUNS")"

  awk -v n="$N" -v off="$OFF" -v on="$ON" 'BEGIN {
    printf "FILES=%-4d  OFF=%8.3f ms  ON=%8.3f ms  speedup=%.2fx  saved=%.3f ms\n", n, off, on, off/on, off-on
  }'
done
