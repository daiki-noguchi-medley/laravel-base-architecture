# PHP 8.4 FPM (Debian Bookworm ベース)
# - 日本語ロケール / Asia/Tokyo
# - Laravel に必要な拡張一式
# - Composer 2
# - Node.js 20 (Vite 用)
# Laravel 13 の依存パッケージが PHP 8.4+ を要求するため 8.4 を採用
FROM php:8.4-fpm-bookworm

# ホスト UID/GID と合わせる (bind mount 上のファイル権限対策)
ARG UID=1000
ARG GID=1000

# ------------------------------------------------------------
# システムパッケージ + ロケール + タイムゾーン
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        ca-certificates \
        gnupg \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        locales \
        tzdata \
        supervisor \
        cron \
    && rm -rf /var/lib/apt/lists/*

# 日本語ロケールを生成 (ja_JP.UTF-8)
RUN sed -i 's/# *\(ja_JP.UTF-8\)/\1/' /etc/locale.gen \
    && locale-gen ja_JP.UTF-8 \
    && update-locale LANG=ja_JP.UTF-8

# タイムゾーンを Asia/Tokyo に
RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone \
    && dpkg-reconfigure -f noninteractive tzdata

ENV LANG=ja_JP.UTF-8 \
    LC_ALL=ja_JP.UTF-8 \
    LANGUAGE=ja_JP:ja \
    TZ=Asia/Tokyo

# ------------------------------------------------------------
# PHP 拡張
# ------------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        gd \
        zip \
        bcmath \
        intl \
        opcache \
        exif \
        pcntl

# ------------------------------------------------------------
# Composer 2 (公式イメージからコピー)
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ------------------------------------------------------------
# Node.js 20 (Vite 用)
# ------------------------------------------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# www-data の UID/GID をホスト側に合わせる
# -o (--non-unique) で衝突しても上書きできるようにする
# ------------------------------------------------------------
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

# ------------------------------------------------------------
# 設定ファイル
# ------------------------------------------------------------
COPY php.ini /usr/local/etc/php/conf.d/zzz-custom.ini
COPY www.conf /usr/local/etc/php-fpm.d/zzz-www.conf

WORKDIR /var/www/html

USER www-data
