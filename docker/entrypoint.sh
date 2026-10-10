#!/bin/sh
# Mykid container entrypoint: make the (possibly freshly mounted) storage volume writable
# for Apache, then start Apache in the foreground (the base image's default command).
set -e
mkdir -p /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage
chmod 775 /var/www/html/storage /var/www/html/storage/uploads
exec "$@"
