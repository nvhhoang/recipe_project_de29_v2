FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && printf '%s\n' \
       'expose_php=Off' \
       'display_errors=Off' \
       'log_errors=On' \
       'cgi.fix_pathinfo=0' \
       'session.use_strict_mode=1' \
       'session.cookie_httponly=1' \
       'session.cookie_samesite=Lax' \
       'memory_limit=128M' \
       'max_execution_time=30' \
       'upload_max_filesize=2M' \
       'post_max_size=2M' \
       > /usr/local/etc/php/conf.d/zz-recipe-security.ini \
    && mkdir -p /var/log/recipe \
    && chown -R www-data:www-data /var/log/recipe \
    && apt-get purge -y --auto-remove libonig-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY src/ /var/www/src/

RUN chown -R root:root /var/www/html /var/www/src \
    && find /var/www/html /var/www/src -type d -exec chmod 0555 {} \; \
    && find /var/www/html /var/www/src -type f -exec chmod 0444 {} \;

EXPOSE 9000

CMD ["php-fpm", "-F"]