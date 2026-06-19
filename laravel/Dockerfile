# 計測精度のため glibc の Debian(bookworm) ベースを使用（Alpine/musl は避ける）
FROM php:8.5-fpm-bookworm

# 拡張インストーラ（依存ライブラリも自動解決）
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        opcache \
        intl \
        zip \
        bcmath \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 依存解決を層キャッシュに乗せる（DBは使わないので pdo 系は入れない）
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . /app

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

# ベース ini と、環境変数から OPcache/JIT を生成するエントリポイント
COPY docker/php.ini /usr/local/etc/php/conf.d/zzz-base.ini
COPY docker/php-fpm-entrypoint.sh /usr/local/bin/php-fpm-entrypoint
RUN chmod +x /usr/local/bin/php-fpm-entrypoint

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/php-fpm-entrypoint"]
CMD ["php-fpm"]
