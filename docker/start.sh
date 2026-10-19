#!/bin/sh
set -e

PORT="${PORT:-80}"

# Point Apache at the port Render allocated for this service.
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
