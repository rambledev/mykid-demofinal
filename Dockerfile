# Mykid — PHP 8.3 + Apache image (Coolify / any Docker host)
#
# The app is plain PHP (no framework, no Composer, no Node.js) + MariaDB via PDO. Built-in extensions
# cover mbstring, json, session, hash, pcre and fileinfo; pdo_mysql is added for MariaDB and GD
# (JPEG/PNG/WebP) for image resizing. Database settings come from env vars: DB_HOST, DB_PORT,
# DB_NAME, DB_USER, DB_PASS (tables are created + seeded on the first request).
# Uploaded images are written to storage/uploads — mount a volume on storage/ to keep them.

FROM php:8.3-apache

# GD with JPEG / PNG / WebP — used to resize & re-encode uploaded images (max 1600px).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Production PHP defaults (display_errors off, errors logged) + small hardening.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'expose_php = Off'; \
        echo 'date.timezone = Asia/Bangkok'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.cookie_samesite = Lax'; \
        echo 'upload_max_filesize = 12M'; \
        echo 'post_max_size = 64M'; \
        echo 'max_file_uploads = 10'; \
        echo 'memory_limit = 256M'; \
    } > "$PHP_INI_DIR/conf.d/zz-mykid.ini"

# Apache: web root = public/ (front controller + assets only); app/, database/, storage/ stay private.
COPY docker/apache/mykid.conf /etc/apache2/conf-available/mykid.conf
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!' /etc/apache2/sites-available/000-default.conf \
    && a2enmod headers \
    && a2enconf mykid

# Application code (read-only for Apache) + writable storage (uploaded images).
COPY . /var/www/html/
RUN mkdir -p /var/www/html/storage \
    && chown -R root:root /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod 775 /var/www/html/storage \
    && chmod 755 /var/www/html/docker/entrypoint.sh

# A mounted storage volume may arrive root-owned: the entrypoint fixes ownership on every start.
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]

# App mode: "production" turns off developer logs (PHP error_log + browser console).
# Override in Coolify with MYKID_ENV=development when debugging.
# Required in Coolify: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_KEY (32+ random chars).
# DEMO_MODE=1 enables the one-tap demo accounts (testing only).
# TRUST_PROXY=1: the app runs behind Coolify's Traefik, so the client IP (login throttling) is read
# from X-Forwarded-For. Set it to 0 if the container is ever exposed without a proxy.
ENV MYKID_ENV=production \
    DEMO_MODE=0 \
    TRUST_PROXY=1

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/healthz > /dev/null || exit 1

