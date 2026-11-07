FROM alpine:3.22

RUN apk add --no-cache \
        composer \
        git \
        php83 \
        php83-bcmath \
        php83-curl \
        php83-dom \
        php83-fileinfo \
        php83-intl \
        php83-mbstring \
        php83-opcache \
        php83-openssl \
        php83-pdo \
        php83-pdo_pgsql \
        php83-phar \
        php83-session \
        php83-tokenizer \
        php83-xml \
        php83-xmlreader \
        php83-xmlwriter \
        php83-zip \
        unzip \
    && ln -s /usr/bin/php83 /usr/local/bin/php \
    && php -v \
    && composer --version

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
