#!/bin/bash
set -e

# ============================================================
# Configuration — set via env vars, adjust per project
# ============================================================
DOMAIN="${DEPLOY_DOMAIN:?}"
APP_DIR="${DEPLOY_APP_DIR:-/var/www/$DOMAIN}"
APP_URL="${DEPLOY_APP_URL:-https://$DOMAIN}"
BRANCH="${DEPLOY_BRANCH:-main}"

APP_USER="ubuntu"
APP_GROUP="www-data"
WRITABLE_DIRS="storage bootstrap/cache lang"
DIR_PERMS="2775"  # shared-server: 2770
FILE_PERMS="664"  # shared-server: 660
PHP_FPM_SERVICE="php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm"

# ============================================================
# Environment (prod|staging)
# ============================================================
ENV="${1:-prod}"

case "$ENV" in
  prod)    COMPOSER_FLAGS="--no-dev" ;;
  staging) COMPOSER_FLAGS="" ;;
  *)
    echo "Usage: $0 {prod|staging}" >&2
    exit 1
    ;;
esac

# ============================================================
# Helpers
# ============================================================
step() { echo -e "\n\033[1;34m→ $1\033[0m"; }
ok()   { echo "  ✓ $1"; }
skip() { echo "  – skipped: $1"; }

artisan_exists() { php artisan list --raw 2>/dev/null | grep -q "^$1 "; }

cd "$APP_DIR"

echo -e "\033[1;33m● Environment: $ENV\033[0m"
echo -e "\033[1;33m● Directory:   $APP_DIR\033[0m"
echo -e "\033[1;33m● URL:         $APP_URL\033[0m"

# ============================================================
# Maintenance mode
# ============================================================
step "Entering maintenance mode"
(php artisan down --retry=15) || true

# ============================================================
# Fix ownership before git (cron/www-data may have changed files)
# ============================================================
step "Fixing ownership for git"
sudo chown -R "$APP_USER:$APP_GROUP" .

# ============================================================
# Pull source
# ============================================================
step "Pulling latest changes"
git fetch origin
git reset --hard "origin/$BRANCH"
git clean -df -e lang/

# ============================================================
# Ensure writable directories exist
# ============================================================
mkdir -p lang

# ============================================================
# Composer
# ============================================================
step "Installing composer dependencies"
composer install $COMPOSER_FLAGS --optimize-autoloader --no-interaction

# ============================================================
# NPM (only if package.json exists)
# ============================================================
step "Building frontend assets"
if [ -f "package.json" ]; then
    rm -rf node_modules
    npm ci --no-audit --no-fund
    npm run build
    ok "npm build complete"
else
    skip "no package.json"
fi

# ============================================================
# Database
# ============================================================
step "Running database migrations"
php artisan migrate --force
ok "migrations complete"

php artisan auth:clear-resets
ok "expired password reset tokens cleared"

# ============================================================
# Filament assets (if installed)
# ============================================================
step "Publishing package assets"
if artisan_exists "filament:assets"; then
    php artisan filament:assets --quiet
    ok "Filament assets published"
else
    skip "Filament not installed"
fi

# ============================================================
# Storage symlink
# ============================================================
step "Ensuring storage symlink"
php artisan storage:link 2>/dev/null || true

# ============================================================
# Cache
# ============================================================
step "Rebuilding cache"
php artisan optimize:clear
php artisan cache:clear
if artisan_exists "filament:optimize-clear"; then
    php artisan filament:optimize-clear --quiet
    ok "Filament cache cleared"
fi
php artisan optimize
if artisan_exists "filament:optimize"; then
    php artisan filament:optimize --quiet
    ok "Filament components and icons cached"
fi
ok "config, routes, views, app cache cleared & rebuilt"

# ============================================================
# Custom project commands (non-blocking)
# ============================================================
step "Running project-specific tasks"
if artisan_exists "app:update-geoip-database"; then
    (php artisan app:update-geoip-database) || skip "GeoIP update failed (non-critical)"
fi

# ============================================================
# Queue workers
# ============================================================
step "Restarting queue workers"
php artisan queue:restart
ok "queue restart signal sent"

# ============================================================
# Permissions
# ============================================================
step "Fixing permissions"
sudo chown -R "$APP_USER:$APP_GROUP" .

for dir in $WRITABLE_DIRS; do
    if [ -d "$dir" ]; then
        sudo chown -R "$APP_GROUP:$APP_GROUP" "$dir"
        sudo find "$dir" -type d -exec chmod "$DIR_PERMS" {} \;
        sudo find "$dir" -type f -exec chmod "$FILE_PERMS" {} \;
        ok "$dir → owner:$APP_GROUP dirs:$DIR_PERMS files:$FILE_PERMS"
    fi
done

sudo chmod +x artisan

# ============================================================
# OPcache + Nginx
# ============================================================
step "Reloading PHP-FPM (clearing OPcache)"
sudo systemctl reload "$PHP_FPM_SERVICE"
ok "$PHP_FPM_SERVICE reloaded"

step "Reloading Nginx (clearing open_file_cache)"
sudo systemctl reload nginx
ok "Nginx reloaded"

# ============================================================
# Go live
# ============================================================
step "Exiting maintenance mode"
php artisan up

step "Health check"
HTTP_STATUS=$(curl -so /dev/null -w "%{http_code}" "$APP_URL")
if [ "$HTTP_STATUS" -eq 200 ]; then
    ok "$APP_URL → $HTTP_STATUS"
else
    echo "  ✗ Health check failed: $APP_URL → $HTTP_STATUS"
    exit 1
fi

echo -e "\n\033[1;32m✓ Deploy complete ($ENV)\033[0m"
