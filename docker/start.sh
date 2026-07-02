#!/bin/sh
set -e

PORT="${PORT:-80}"
sed -i "s/\*:80/*:${PORT}/" /etc/apache2/sites-available/000-default.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

# Exportar variáveis de ambiente para o Apache
# O Apache precisa ver as variáveis de ambiente do Render/host
export DB_URL
export DB_SSL
export DB_SSL_CA
export MAIL_USERNAME
export MAIL_PASSWORD
export VAPID_PUBLIC_KEY
export VAPID_PRIVATE_KEY
export VAPID_SUBJECT
export PUSH_ADMIN_SECRET
export BACKEND_URL
export FRONTEND_URL
export CRON_SECRET

exec apache2-foreground
