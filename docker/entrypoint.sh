#!/bin/sh
set -eu

mkdir -p /var/www/html/database /var/www/html/private_uploads /var/www/html/storage/logs /var/www/html/storage/tmp
chmod -R 777 /var/www/html/database /var/www/html/private_uploads /var/www/html/storage || true

exec "$@"
