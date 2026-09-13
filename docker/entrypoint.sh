#!/bin/sh
set -e

export PORT="${PORT:-8080}"
export GPS_SERVICE_URL="${GPS_SERVICE_URL:-https://smartbus-gps-tracking.onrender.com}"

echo "=== Preparando API Gateway ==="

# Reemplazar $PORT y $GPS_SERVICE_URL en la plantilla de Nginx
envsubst '$PORT $GPS_SERVICE_URL' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Cachear configuraciones y rutas del Gateway
php artisan config:cache
php artisan route:cache

echo "=== Iniciando Supervisor (Nginx + Laravel Gateway) ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf