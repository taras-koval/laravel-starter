# Laravel Starter

A development-only package that publishes and updates a shared set of Laravel starter files into an existing project through a single Artisan command. Use it to bootstrap new projects and keep existing ones in sync with the latest starter improvements.

**Requirements:** Laravel 13 and PHP 8.3+.

## Quickstart

The full sequence for a brand-new API project:

```bash
# 1. Create a new Laravel project
composer global update laravel/installer --with-all-dependencies
laravel new app --git && cd my-api

# 2. Install the starter package
composer require --dev taras-koval/laravel-starter

# 3. Install the stub dependencies
composer require laravel/sanctum dedoc/scramble spatie/laravel-query-builder geoip2/geoip2 jenssegers/agent mcamara/laravel-localization

# 4. Publish all starter files
php artisan starter:publish

# 5. Run migrations
php artisan migrate

# 6. Create the public storage symlink
php artisan storage:link

# 7. Generate AI config files (if using Laravel Boost)
php artisan boost:install
```

## Installation

Install the package as a development dependency:

```bash
composer require --dev taras-koval/laravel-starter
```

### Local development

When working on the package itself, register it as a path repository with a symlink so changes are reflected immediately without reinstalling, then require it:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/laravel-starter",
        "options": { "symlink": true }
    }
]
```

```bash
composer require --dev taras-koval/laravel-starter
```

## Prerequisites

The published stubs reference third-party packages. The starter package **does not** require them itself — they are declared under `suggest` so they remain explicit in the consuming project's `composer.json`. Install them **before** running `starter:publish`.

Install everything at once:

```bash
composer require laravel/sanctum dedoc/scramble spatie/laravel-query-builder geoip2/geoip2 jenssegers/agent mcamara/laravel-localization
```

Or install only what a specific feature requires:

```bash
# API token authentication (User model, AuthController, SessionResource)
composer require laravel/sanctum

# OpenAPI documentation generation
composer require dedoc/scramble

# Includable relations feature
composer require spatie/laravel-query-builder

# GeoIP session metadata (RequestMetadataService, UpdateGeoIpDatabaseCommand)
composer require geoip2/geoip2

# Device detection in sessions (RequestMetadataService)
composer require jenssegers/agent

# Localized web routes
composer require mcamara/laravel-localization
```

| Package                        | Version | Purpose                          |
|--------------------------------|---------|----------------------------------|
| `laravel/sanctum`              | `^4.3`  | API token authentication         |
| `dedoc/scramble`               | `^0.13` | OpenAPI documentation generation |
| `spatie/laravel-query-builder` | `^7.2`  | Includable relations feature     |
| `geoip2/geoip2`                | `^3.3`  | GeoIP session metadata           |
| `jenssegers/agent`             | `^2.6`  | Device detection in sessions     |
| `mcamara/laravel-localization` | `^2.4`  | Localized web routes             |

## Usage

A single command applies every change:

```bash
php artisan starter:publish
```

> **Warning:** Publishing overwrites the files listed under [Overwritten](#overwritten-always-replaced) without confirmation. Commit or stash your work first so you can review the diff afterwards.

The command uses three publishing strategies, described below.

### Overwritten (always replaced)

| Target                                                                        | Notes                                                                                                           |
|-------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `app/`                                                                        | Whole directory: controllers, models, requests, resources, services, commands                                   |
| `resources/`                                                                  | CSS, JS, and Blade views (welcome, error pages, layouts)                                                        |
| `tests/`                                                                      | Feature and unit tests for the published controllers (`TestCase`, auth, user, example)                          |
| `bootstrap/app.php`                                                           | Routing and middleware config (registers `routes/auth.php` and `throttleApi()`)                                 |
| `routes/auth.php`                                                             | Starter API routes                                                                                              |
| `database/factories/UserFactory.php`                                          |                                                                                                                 |
| `database/migrations/0001_01_01_000000_create_users_table.php`                |                                                                                                                 |
| `config/app.php`, `config/auth.php`, `config/cache.php`                       |                                                                                                                 |
| `config/cors.php`, `config/filesystems.php`, `config/logging.php`             |                                                                                                                 |
| `config/laravellocalization.php`, `config/sanctum.php`, `config/services.php` |                                                                                                                 |
| `.env.example`, `.gitignore`, `package.json`, `phpunit.xml`, `pint.json`      | Root config files                                                                                               |
| `boost.json`                                                                  | Laravel Boost config (regenerate guidelines via `php artisan boost:install`)                                    |
| `.cursor/rules/`                                                              | Cursor AI rules (enum naming, etc.) — committed to the project, safe to extend                                  |
| `.github/`                                                                    | Deploy workflows and `deploy.sh` (auto-deploy disabled by default — see [CI/CD auto-deploy](#cicd-auto-deploy)) |

`bootstrap/app.php` is overwritten wholesale, so it already registers `routes/auth.php` (under the `api` middleware group and `/api` prefix). Keep your own application routes in the host-owned `routes/api.php`; the starter routes live in the standalone, overwritable `routes/auth.php`.

### Copied only when missing

| Target               | Condition                                                                                                                     |
|----------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `routes/api.php`     | Copied only when the file does not already exist; otherwise left untouched                                                    |
| `routes/web.php`     | Copied only when the file does not already contain `LaravelLocalization::setLocale()`; otherwise left untouched               |
| `routes/console.php` | Copied only when the file does not already contain `Schedule::command('app:update-geoip-database')`; otherwise left untouched |
| `README.md`          | Copied only when the file does not already contain `Trusted Proxies`; otherwise left untouched                                |

### Migrations (published with a fresh timestamp)

| Target                                      | Condition                                                                                                                   |
|---------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `…_create_personal_access_tokens_table.php` | Published with today's timestamp only when no existing migration matches `create_personal_access_tokens`; otherwise skipped |

## Post-install steps

### Storage symlink

After publishing, create the public storage symlink:

```bash
php artisan storage:link
```

### Laravel Boost

The starter ships `boost.json` (the shared Boost config), but the generated AI guidelines and MCP configs are **not** published — they embed machine-specific absolute paths and version-pinned guidelines, and are git-ignored (`.cursor/`, `.ai/`, `AGENTS.md`). Regenerate them locally after publishing:

```bash
php artisan boost:install
```

This recreates `AGENTS.md`, `.cursor/*`, and `.ai/mcp/mcp.json` for the local machine and the project's installed package versions.

If you prefer to copy the MCP configs manually instead of running the installer, use the templates below and replace every path that points at this project (the PHP binary, the project root, and `SITE_PATH`) with the new project's values.

`.ai/mcp/mcp.json`:

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "C:\\Users\\Taras\\.config\\herd\\bin\\php84\\php.exe",
      "args": [
        "S:\\herd\\YOUR-PROJECT\\artisan",
        "boost:mcp"
      ]
    },
    "herd": {
      "command": "C:\\Users\\Taras\\.config\\herd\\bin\\php84\\php.exe",
      "args": [
        "C:/Users/Taras/.config/herd/bin/herd-mcp.phar"
      ],
      "env": {
        "SITE_PATH": "S:\\herd\\YOUR-PROJECT"
      }
    }
  }
}
```

`.cursor/mcp.json`:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": [
                "artisan",
                "boost:mcp"
            ]
        },
        "herd": {
            "command": "php",
            "args": [
                "C:/Users/Taras/.config/herd/bin/herd-mcp.phar"
            ],
            "env": {
                "SITE_PATH": "S:\\herd\\YOUR-PROJECT"
            }
        }
    }
}
```

## Trusted Proxies

The published application reads the client IP from `X-Forwarded-*` headers when running behind a reverse proxy or load balancer. To prevent IP spoofing, you must tell Laravel which proxies to trust via the `TRUSTED_PROXIES` environment variable.

### Configuration

| Value                                | Behavior                                                                                                                                                     |
|--------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TRUSTED_PROXIES=` *(empty)*         | Trust no proxy. `X-Forwarded-*` headers are ignored and `request()->ip()` returns the real TCP remote address. Safe default when no proxy is present.        |
| `TRUSTED_PROXIES=*`                  | Trust **any** proxy. Use behind AWS ELB, Heroku, Fly.io, or temporarily for local GeoIP testing (send a fake `X-Forwarded-For: 8.8.8.8` header via Postman). |
| `TRUSTED_PROXIES=1.2.3.4,10.0.0.0/8` | Trust specific IPs and CIDR ranges only (for example, [Cloudflare IP ranges](https://www.cloudflare.com/ips/)).                                              |

### When to use each option

| Environment                                             | Recommended value                                                                                                                    |
|---------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|
| Local development (Herd, `php artisan serve`, no proxy) | Leave empty                                                                                                                          |
| Local geo testing                                       | Set `*` temporarily to send a fake `X-Forwarded-For: 8.8.8.8` header via Postman and exercise GeoIP code paths, then revert to empty |
| Behind Cloudflare                                       | The official [Cloudflare IP ranges](https://www.cloudflare.com/ips/), comma-separated — or `*` if Cloudflare is your only ingress    |
| Behind AWS ELB / Heroku / Fly.io                        | `*` (these platforms don't expose stable proxy IPs)                                                                                  |
| Direct VPS without any proxy                            | Leave empty, which makes header-based IP spoofing impossible                                                                         |

### Why it matters

Without trusted-proxy configuration, an attacker can send an arbitrary `X-Forwarded-For` header on every request. Laravel reads this as the client IP and uses it for:

- **Rate limiting** (login throttle, API throttle) — circumvented.
- **GeoIP detection** — falsified country and city in `personal_access_tokens`.
- **Audit logs** — rendered useless for tracking the real source.

Trusting only specific proxies makes any spoofing attempt fall back to the real `REMOTE_ADDR`.

## CI/CD auto-deploy

The published `.github/workflows/deploy-prod.yml` and `deploy-staging.yml` ship with the `push` trigger **commented out**, so a freshly cloned project will not deploy on push. Only `workflow_dispatch` (manual run) is active.

To enable auto-deploy on a new project:

1. **Set the workflow env values** in each workflow file:
   - `DEPLOY_DOMAIN` — your domain (for example, `app.example.com`, `staging.example.com`)
   - `DEPLOY_BRANCH` — branch to deploy (`main` for production, `develop` for staging)
2. **Add the repository secrets** (Settings → Secrets and variables → Actions):
   - `SSH_HOST` — server host or IP
   - `SSH_USERNAME` — SSH user
   - `SSH_PORT` — SSH port
   - `SSH_KEY` — private SSH key
3. **Uncomment the `push` trigger** at the top of each workflow file.

`deploy.sh` is environment-driven and usually needs no edits, but review these defaults at the top of the script for your server:

- `APP_USER` / `APP_GROUP` — file ownership (defaults `ubuntu` / `www-data`)
- `DEPLOY_APP_DIR` — overrides the app path (defaults `/var/www/$DEPLOY_DOMAIN`)
- `DEPLOY_APP_URL` — overrides the health check URL (defaults `https://$DEPLOY_DOMAIN`)
