#!/bin/sh
set -e

export PORT="${PORT:-8080}"
export GPS_SERVICE_URL="${GPS_SERVICE_URL:-https://smartbus-gps-tracking.onrender.com}"
export GPS_SERVICE_HOST="${GPS_SERVICE_HOST:-smartbus-gps-tracking.onrender.com}"
export DNS_RESOLVER="${DNS_RESOLVER:-8.8.8.8 1.1.1.1}"

echo "=== Preparing API Gateway ==="
echo "PORT: $PORT"
echo "GPS_SERVICE_URL: $GPS_SERVICE_URL"
echo "GPS_SERVICE_HOST: $GPS_SERVICE_HOST"
echo "DNS_RESOLVER: $DNS_RESOLVER"

# Replace runtime values in the Nginx template
envsubst '$PORT $GPS_SERVICE_URL $GPS_SERVICE_HOST $DNS_RESOLVER' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Cache configurations and routes for the Gateway
php artisan config:cache
php artisan route:cache

echo "=== Starting Supervisor (Nginx + Laravel Gateway) ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf