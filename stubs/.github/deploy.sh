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
WEB_USER="www-data"  # user php-fpm runs as
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

# Runs artisan as the web user, so files it creates in storage/ and bootstrap/cache
# (compiled views, caches, logs) are owned by php-fpm's user. Otherwise php-fpm fails on
# them with "touch(): Uptime failed: Operation not permitted" while the deploy is running.
www_artisan() { sudo -u "$WEB_USER" php artisan "$@"; }

artisan_exists() { www_artisan list --raw 2>/dev/null | grep -q "^$1 "; }

# Gives the deploy user the whole tree except storage/, which stays owned by the web user.
fix_repo_ownership() {
    sudo find . -path ./storage -prune -o -exec chown -h "$APP_USER:$APP_GROUP" {} +
}

cd "$APP_DIR"

echo -e "\033[1;33m● Environment: $ENV\033[0m"
echo -e "\033[1;33m● Directory:   $APP_DIR\033[0m"
echo -e "\033[1;33m● URL:         $APP_URL\033[0m"

# ============================================================
# Maintenance mode
# ============================================================
step "Entering maintenance mode"
(www_artisan down --retry=15) || true

# ============================================================
# Fix ownership before git (cron/www-data may have changed files)
# ============================================================
step "Fixing ownership for git"
fix_repo_ownership

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
www_artisan migrate --force
ok "migrations complete"

www_artisan auth:clear-resets
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
if [ -L public/storage ]; then
    skip "public/storage link already exists"
else
    php artisan storage:link
    ok "storage link created"
fi

# ============================================================
# Cache
# ============================================================
step "Rebuilding cache"
www_artisan optimize:clear
www_artisan cache:clear
if artisan_exists "filament:optimize-clear"; then
    www_artisan filament:optimize-clear --quiet
    ok "Filament cache cleared"
fi
# Not `php artisan optimize`: it also runs route:cache, which breaks
# mcamara/laravel-localization (non-default locales return 404).
www_artisan config:cache
www_artisan event:cache
www_artisan view:cache
if artisan_exists "route:trans:cache"; then
    skip "route cache (mcamara/laravel-localization is installed)"
else
    www_artisan route:cache
    ok "routes cached"
fi
if artisan_exists "filament:optimize"; then
    www_artisan filament:optimize --quiet
    ok "Filament components and icons cached"
fi
ok "config, events, views cached"

# ============================================================
# Custom project commands (non-blocking)
# ============================================================
step "Running project-specific tasks"
if artisan_exists "app:update-geoip-database"; then
    (www_artisan app:update-geoip-database) || skip "GeoIP update failed (non-critical)"
fi

# ============================================================
# Queue workers
# ============================================================
step "Restarting queue workers"
www_artisan queue:restart
ok "queue restart signal sent"

# Horizon's master process keeps the old code and config/horizon.php until terminated;
# the process monitor (Supervisor/systemd) is expected to start it again.
if artisan_exists "horizon:terminate"; then
    if www_artisan horizon:status 2>/dev/null | grep -qi "running"; then
        www_artisan horizon:terminate
        ok "Horizon terminate signal sent"
    else
        skip "Horizon is not running"
    fi
fi

# ============================================================
# Permissions
# ============================================================
step "Fixing permissions"
fix_repo_ownership

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
www_artisan up

step "Health check"
HTTP_STATUS=$(curl -so /dev/null -w "%{http_code}" "$APP_URL")
if [ "$HTTP_STATUS" -eq 200 ]; then
    ok "$APP_URL → $HTTP_STATUS"
else
    echo "  ✗ Health check failed: $APP_URL → $HTTP_STATUS"
    exit 1
fi

echo -e "\n\033[1;32m✓ Deploy complete ($ENV)\033[0m"
