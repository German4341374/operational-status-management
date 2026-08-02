#!/bin/sh
set -eu

php /var/www/html/bin/migrate.php
exec "$@"
