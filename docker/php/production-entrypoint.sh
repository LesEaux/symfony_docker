#!/bin/sh
set -eu
if [ "$1" = "php-fpm" ]; then
    php bin/console cache:warmup --env=prod --no-debug
fi
exec docker-php-entrypoint "$@"
