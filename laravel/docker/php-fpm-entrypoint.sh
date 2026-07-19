#!/usr/bin/env sh
set -eu

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --force
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

# ------------------------------------------------------------------
# 環境変数 → OPcache/JIT 設定を起動時に生成（リビルド不要で切替可能）
# ------------------------------------------------------------------
: "${OPCACHE_ENABLE:=1}"
: "${OPCACHE_ENABLE_CLI:=1}"
: "${OPCACHE_VALIDATE_TIMESTAMPS:=1}"
: "${OPCACHE_JIT:=tracing}"          # tracing / function / disable / 数値CRTO(例 1255)
: "${OPCACHE_JIT_BUFFER_SIZE:=128M}" # 0 で JIT 無効
: "${OPCACHE_MEMORY:=256}"           # MB
: "${OPCACHE_INTERNED_STRINGS_BUFFER:=16}" # MB（OPcache 共有メモリの内数）
: "${OPCACHE_MAX_FILES:=20000}"      # キャッシュできる PHP ファイル数の上限

cat > /usr/local/etc/php/conf.d/zzz-perf.ini <<EOF
[opcache]
opcache.enable=${OPCACHE_ENABLE}
opcache.enable_cli=${OPCACHE_ENABLE_CLI}
opcache.validate_timestamps=${OPCACHE_VALIDATE_TIMESTAMPS}
opcache.memory_consumption=${OPCACHE_MEMORY}
opcache.interned_strings_buffer=${OPCACHE_INTERNED_STRINGS_BUFFER}
opcache.max_accelerated_files=${OPCACHE_MAX_FILES}
opcache.jit=${OPCACHE_JIT}
opcache.jit_buffer_size=${OPCACHE_JIT_BUFFER_SIZE}
EOF

echo "[entrypoint] OPcache=${OPCACHE_ENABLE} memory=${OPCACHE_MEMORY}M max_files=${OPCACHE_MAX_FILES} JIT=${OPCACHE_JIT} jit_buffer=${OPCACHE_JIT_BUFFER_SIZE}"
php -v
php -i | grep -Ei 'opcache.enable|jit' | sed 's/^/[php -i] /' || true

# php-fpm（または docker run で渡されたコマンド）を実行
exec "$@"
