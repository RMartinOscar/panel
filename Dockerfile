# Pelican Production Dockerfile

FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Copy the application code to the container
COPY . .

# Install dependencies
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk update && apk add --no-cache \
    libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev \
    zip unzip curl caddy ca-certificates supervisor nodejs yarn

RUN docker-php-ext-install bcmath gd intl zip opcache pcntl posix pdo_mysql

RUN composer install --no-dev --optimize-autoloader

RUN yarn config set network-timeout 300000 \
    && yarn install --frozen-lockfile \
    && yarn run build

# Copy the Caddyfile to the container
COPY Caddyfile /etc/caddy/Caddyfile

RUN touch .env

# Set file permissions
RUN chmod -R 755 storage bootstrap/cache \
    && chown -R www-data:www-data ./

# Add scheduler to cron
RUN echo "* * * * * php /var/www/html/artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -

## supervisord config and log dir
RUN cp .github/docker/supervisord.conf /etc/supervisord.conf \
    && mkdir /var/log/supervisord/

HEALTHCHECK --interval=5m --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/up || exit 1

EXPOSE 80 443

VOLUME /pelican-data

ENTRYPOINT [ "/bin/ash", ".github/docker/entrypoint.sh" ]
CMD [ "supervisord", "-n", "-c", "/etc/supervisord.conf" ]
