# Alpine 公式 nginx ベース
#   - apk install で brotli モジュールが楽に入る
#     (nginx 公式 image だと apk の brotli モジュールが ABI 不一致でロード不可)
#   - タイムゾーンを Asia/Tokyo に設定
FROM alpine:3.21

RUN apk add --no-cache \
        nginx \
        nginx-mod-http-brotli \
        tzdata \
    && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone \
    # Alpine の nginx パッケージは /run/nginx を作らないので手動で
    && mkdir -p /run/nginx

ENV TZ=Asia/Tokyo

# Alpine 公式 nginx は /etc/nginx/http.d/ を使う (Debian 系の conf.d 相当)
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/http.d/default.conf
# 各 location で include するセキュリティヘッダ snippet
COPY security-headers.conf /etc/nginx/snippets/security-headers.conf

EXPOSE 80

# nginx 公式 image と挙動を揃えるため foreground 起動
CMD ["nginx", "-g", "daemon off;"]
