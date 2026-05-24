# PostgreSQL 16 (Debian Bookworm ベース)
# 日本語ロケール (ja_JP.UTF-8) と Asia/Tokyo を設定
FROM postgres:16-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends locales tzdata \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/# *\(ja_JP.UTF-8\)/\1/' /etc/locale.gen \
    && locale-gen ja_JP.UTF-8 \
    && update-locale LANG=ja_JP.UTF-8 \
    && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone

ENV LANG=ja_JP.UTF-8 \
    LC_ALL=ja_JP.UTF-8 \
    TZ=Asia/Tokyo
