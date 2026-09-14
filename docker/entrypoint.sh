#!/bin/sh
set -e

export PORT="${PORT:-8080}"
export GPS_SERVICE_URL="${GPS_SERVICE_URL:-https://smartbus-gps-tracking.onrender.com}"
export GPS_SERVICE_HOST="${GPS_SERVICE_HOST:-smartbus-gps-tracking.onrender.com}"

echo "=== Preparando API Gateway ==="

# Reemplazar $PORT, $GPS_SERVICE_URL y $GPS_SERVICE_HOST en la plantilla de Nginx
envsubst '$PORT $GPS_SERVICE_URL $GPS_SERVICE_HOST' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Cachear configuraciones y rutas del Gateway
php artisan config:cache
php artisan route:cache

echo "=== Iniciando Supervisor (Nginx + Laravel Gateway) ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf