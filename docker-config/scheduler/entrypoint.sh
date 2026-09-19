#!/bin/sh
set -e

CRON_FILE=/etc/cron.d/laravel-scheduler

# Laravel scheduler: run every minute as www-data (matches the app container user).
printf '%s\n' \
  '* * * * * www-data cd /var/www && /usr/local/bin/php artisan schedule:run --no-interaction >> /proc/1/fd/1 2>>/proc/1/fd/2' \
  > "${CRON_FILE}"

chmod 0644 "${CRON_FILE}"

echo "Starting cron for Laravel scheduler (schedule:run every minute)..."

exec cron -f
