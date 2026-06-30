#!/bin/sh
set -e

PORT="${PORT:-80}"
sed -i "s/\*:80/*:${PORT}/" /etc/apache2/sites-available/000-default.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

exec apache2-foreground
