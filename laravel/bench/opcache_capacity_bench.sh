#!/usr/bin/env sh
set -eu

# ---------------------------------------------------------------------------
# PHP-FPM + nginx を使い、同じ Laravel Welcome ページを次の条件で計測する。
#
#   OFF         : OPcache 無効
#   normal      : memory=256MB / max_accelerated_files=20000
#   constrained : memory=8MB   / max_accelerated_files=200（PHP 仕様上の最小値）
#
# エンドポイント:
#   /                         … Laravel デフォルト Welcome ページ
#   /perf/dependency-heavy    … Welcome + サービスコンテナで 36 依存を解決
#   /perf/opcache-pressure    … Welcome + 400 PHP ファイルを require
#
# 最後のエンドポイントは constrained 条件で cache_full=true または
# cached_keys=max_cached_keys を確実に観測するための負荷。レスポンス時間だけでなく、
# /perf/status の OPcache 統計も出力して「本当にキャッシュが不足したか」を確認する。
#
# GitHub Actions ubuntu-latest 上（laravel/ をカレントディレクトリ）:
#   sh bench/opcache_capacity_bench.sh performance-php-app 30 5 400
# ---------------------------------------------------------------------------

IMAGE="${1:-performance-php-app}"
RUNS="${2:-30}"
WARMUPS="${3:-5}"
PRESSURE_FILES="${4:-400}"
PROJECT_DIR="$(pwd)"
APP_CONTAINER=""
WEB_CONTAINER=""
NETWORK=""

cleanup() {
  if [ -n "$WEB_CONTAINER" ]; then
    docker rm -f "$WEB_CONTAINER" >/dev/null 2>&1 || true
    WEB_CONTAINER=""
  fi
  if [ -n "$APP_CONTAINER" ]; then
    docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
    APP_CONTAINER=""
  fi
  if [ -n "$NETWORK" ]; then
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    NETWORK=""
  fi
}

trap cleanup EXIT INT TERM

warmup() {
  _url="$1"
  _i=0

  while [ "$_i" -lt "$WARMUPS" ]; do
    curl --fail --silent --show-error --output /dev/null "$_url"
    _i=$((_i + 1))
  done
}

measure() {
  _label="$1"
  _url="$2"
  _i=0
  _samples="$(mktemp)"

  while [ "$_i" -lt "$RUNS" ]; do
    curl --fail --silent --show-error --output /dev/null \
      --write-out '%{time_total}\n' "$_url" >> "$_samples"
    _i=$((_i + 1))
  done

  awk -v label="$_label" '
    NR == 1 { min = $1; max = $1 }
    { sum += $1; if ($1 < min) min = $1; if ($1 > max) max = $1 }
    END {
      printf "%-20s avg=%8.3f ms  min=%8.3f ms  max=%8.3f ms  runs=%d\n",
        label, (sum / NR) * 1000, min * 1000, max * 1000, NR
    }
  ' "$_samples"

  rm -f "$_samples"
}

print_status() {
  _base_url="$1"

  curl --fail --silent --show-error "${_base_url}/perf/status" | jq -r '
    "OPcache status: " + (
      {
        enabled: .opcache.enabled,
        configured_memory: .directives["opcache.memory_consumption"],
        configured_interned_strings: .directives["opcache.interned_strings_buffer"],
        configured_max_files: .directives["opcache.max_accelerated_files"],
        cache_full: .opcache.cache_full,
        cached_scripts: .opcache.cached_scripts,
        cached_keys: .opcache.cached_keys,
        max_cached_keys: .opcache.max_cached_keys,
        used_memory_mb: ((.opcache.memory_usage.used_memory // 0) / 1048576 * 100 | round / 100),
        free_memory_mb: ((.opcache.memory_usage.free_memory // 0) / 1048576 * 100 | round / 100),
        oom_restarts: .opcache.oom_restarts,
        hash_restarts: .opcache.hash_restarts
      } | tojson
    )
  '
}

start_case() {
  _case="$1"
  _enabled="$2"
  _memory="$3"
  _interned="$4"
  _max_files="$5"

  cleanup
  NETWORK="opcache-capacity-${_case}-$$"
  APP_CONTAINER="opcache-capacity-app-${_case}-$$"
  WEB_CONTAINER="opcache-capacity-web-${_case}-$$"

  docker network create "$NETWORK" >/dev/null

  docker run --detach --rm \
    --name "$APP_CONTAINER" \
    --network "$NETWORK" \
    --network-alias app \
    --env OPCACHE_ENABLE="$_enabled" \
    --env OPCACHE_ENABLE_CLI=0 \
    --env OPCACHE_VALIDATE_TIMESTAMPS=0 \
    --env OPCACHE_MEMORY="$_memory" \
    --env OPCACHE_INTERNED_STRINGS_BUFFER="$_interned" \
    --env OPCACHE_MAX_FILES="$_max_files" \
    --env OPCACHE_JIT=disable \
    --env OPCACHE_JIT_BUFFER_SIZE=0 \
    "$IMAGE" >/dev/null

  # キャッシュ上限超過を再現する PHP ファイル群。CLI の OPcache は無効なので、
  # 生成処理自体は計測対象にも FPM の共有 OPcache にも入らない。
  docker exec "$APP_CONTAINER" \
    php bench/thin_gen.php "$PRESSURE_FILES" /tmp/opcache-pressure >/dev/null 2>&1

  docker run --detach --rm \
    --name "$WEB_CONTAINER" \
    --network "$NETWORK" \
    --publish 127.0.0.1::8000 \
    --volume "${PROJECT_DIR}:/app:ro" \
    --volume "${PROJECT_DIR}/docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro" \
    nginx:1.27-bookworm >/dev/null

  _port="$(docker port "$WEB_CONTAINER" 8000/tcp | awk -F: 'NR == 1 { print $NF }')"
  BASE_URL="http://127.0.0.1:${_port}"
  export BASE_URL

  _attempt=0
  until curl --fail --silent --output /dev/null "${BASE_URL}/"; do
    _attempt=$((_attempt + 1))
    if [ "$_attempt" -ge 30 ]; then
      docker logs "$APP_CONTAINER"
      docker logs "$WEB_CONTAINER"
      return 1
    fi
    sleep 1
  done

  echo
  echo "=== ${_case}: enabled=${_enabled} memory=${_memory}MB interned=${_interned}MB max_files=${_max_files} ==="
}

run_case() {
  _case="$1"
  _enabled="$2"
  _memory="$3"
  _interned="$4"
  _max_files="$5"

  start_case "$_case" "$_enabled" "$_memory" "$_interned" "$_max_files"

  warmup "${BASE_URL}/"
  measure "welcome" "${BASE_URL}/"

  warmup "${BASE_URL}/perf/dependency-heavy"
  measure "welcome + 36 deps" "${BASE_URL}/perf/dependency-heavy"

  print_status "$BASE_URL"

  warmup "${BASE_URL}/perf/opcache-pressure?files=${PRESSURE_FILES}"
  measure "cache pressure" "${BASE_URL}/perf/opcache-pressure?files=${PRESSURE_FILES}"

  print_status "$BASE_URL"
}

echo "[opcache-capacity-bench] image=${IMAGE} runs=${RUNS} warmups=${WARMUPS} pressure_files=${PRESSURE_FILES}"
echo "runner_arch=$(uname -m)"

run_case off 0 256 16 20000
run_case normal 1 256 16 20000
run_case constrained 1 8 1 200
