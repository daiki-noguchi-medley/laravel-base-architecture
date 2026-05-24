# nginx (Alpine ベース)
# タイムゾーンを Asia/Tokyo に設定
FROM nginx:1.27-alpine

# タイムゾーン (tzdata) を導入し Asia/Tokyo にリンク
RUN apk add --no-cache tzdata \
    && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone

ENV TZ=Asia/Tokyo

# Laravel 用 vhost 設定
COPY default.conf /etc/nginx/conf.d/default.conf
