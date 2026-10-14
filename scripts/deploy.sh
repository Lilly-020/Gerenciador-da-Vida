#!/usr/bin/env bash
#
# Updates the live app on the server to the latest origin/main. Run by the
# "Deploy" job in .github/workflows/ci.yml over SSH (the deploy key is
# pinned to this script in ~/.ssh/authorized_keys), or by hand:
#
#   ssh ubuntu@gerenciadorvida.duckdns.org /var/www/app/scripts/deploy.sh

set -euo pipefail

# Wrapped in a function so bash reads the whole file before running it —
# `git pull` below may replace this very script mid-run.
main() {
    cd /var/www/app

    git pull --ff-only origin main

    composer install --no-dev --optimize-autoloader --no-interaction
    npm ci --no-audit --no-fund
    npm run build

    # Migrations run unattended, so keep a copy of the data from just before.
    mkdir -p storage/app/backups
    cp database/database.sqlite storage/app/backups/pre-deploy.sqlite
    php artisan migrate --force

    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    sudo chown -R ubuntu:www-data /var/www/app
    sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
    sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
    sudo chmod 664 database/database.sqlite

    echo "Deployed $(git log --oneline -1)"
}

main "$@"
