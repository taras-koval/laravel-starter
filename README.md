# Laravel Starter

A development-only package that publishes and updates a shared set of Laravel starter files into an existing project through a single Artisan command. Use it to bootstrap new projects and keep existing ones in sync with the latest starter improvements.

**Requirements:** Laravel 13 and PHP 8.3+.

## Quickstart

The full sequence for a brand-new API project:

```bash
# 1. Install the starter package
composer require --dev taras-koval/laravel-starter

# 2. Install the backend dependencies
composer require laravel/sanctum dedoc/scramble spatie/laravel-query-builder geoip2/geoip2 jenssegers/agent mcamara/laravel-localization resend/resend-laravel

# 3. Install the frontend dependencies
npm install -D @tailwindcss/vite alpinejs axios concurrently fontaine laravel-vite-plugin tailwindcss vite

# 4. Publish all starter files
php artisan starter:publish

# 5. Run migrations
php artisan migrate

# 6. Create the public storage symlink
php artisan storage:link

# 7. Generate AI config files (if using Laravel Boost)
php artisan boost:install

# 8. Build the code knowledge graph (if using Graphify; run from the project root, hooks need a git repository)
graphify extract . --code-only
graphify cluster-only . --no-label
graphify hook install
graphify cursor install
```

## Environment configuration

After installing the dependencies and running `starter:publish`, review `.env.example` and configure the corresponding values in `.env` before using the application.

At minimum, configure:

- `APP_URL` — the API application's public URL.
- `FRONTEND_URL` — the URL of the frontend application.
- `CORS_ALLOWED_ORIGINS` — comma-separated frontend origins allowed to make cross-origin requests. Use explicit origins when credentials are enabled; browsers reject `*` with credentials.
- `SANCTUM_STATEFUL_DOMAINS` — frontend hosts that should use Sanctum's cookie-based SPA authentication.
- `DB_*`, `SESSION_*`, `CACHE_*`, and `QUEUE_*` — infrastructure settings for the database, sessions, cache, and queues.
- `MAIL_*` — mail transport and sender settings. With the default `log` mailer, messages are written to `storage/logs/mail.log`.
- `RESEND_API_KEY` — API key for the Resend email service.
- `MAXMIND_ACCOUNT_ID` and `MAXMIND_LICENSE_KEY` — MaxMind credentials required for GeoIP database updates.
- `AWS_*` — credentials and endpoint settings when using S3-compatible storage.

To configure mail with [Resend](https://resend.com), give this prompt to an AI agent:

```text
Fetch and follow the instructions at https://resend.com/setup.md to set up Resend for me.
```

## Usage

A single command applies every change:

```bash
php artisan starter:publish
```

> **Warning:** Publishing overwrites the files listed under [Overwritten](#overwritten-always-replaced) without confirmation. Commit or stash your work first so you can review the diff afterwards.

The command uses three publishing strategies, described below.

### Overwritten (always replaced)

| Target                                                                        | Notes                                                                                                               |
|-------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| `app/`                                                                        | Whole directory except `Http/Middleware/EnsureUser.php` (see [Copied only when missing](#copied-only-when-missing)) |
| `resources/`                                                                  | CSS, JS, and Blade views (welcome, error pages, layouts)                                                            |
| `tests/`                                                                      | Feature and unit tests for the published controllers (`TestCase`, auth, user, example)                              |
| `bootstrap/app.php`                                                           | Routing and middleware config (registers `routes/auth.php` and `throttleApi()`)                                     |
| `routes/auth.php`                                                             | Starter API routes                                                                                                  |
| `database/factories/UserFactory.php`                                          |                                                                                                                     |
| `database/migrations/0001_01_01_000000_create_users_table.php`                |                                                                                                                     |
| `config/app.php`, `config/auth.php`, `config/cache.php`                       |                                                                                                                     |
| `config/cors.php`, `config/filesystems.php`, `config/logging.php`             |                                                                                                                     |
| `config/laravellocalization.php`, `config/sanctum.php`, `config/services.php` |                                                                                                                     |
| `.env.example`, `.gitignore`, `phpunit.xml`, `pint.json`, `vite.config.js`    | Root config files                                                                                                   |
| `.cursor/rules/`                                                              | Cursor AI rules (enum naming, frontend verification via obscura) — committed to the project, safe to extend         |
| `.github/`                                                                    | Deploy workflows and `deploy.sh` (auto-deploy disabled by default — see [CI/CD auto-deploy](#cicd-auto-deploy))     |

`bootstrap/app.php` is overwritten wholesale, so it already registers `routes/auth.php` (under the `api` middleware group and `/api` prefix). Keep your own application routes in the host-owned `routes/api.php`; the starter routes live in the standalone, overwritable `routes/auth.php`.

### Copied only when missing

| Target                               | Condition                                                                                                                     |
|--------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `routes/api.php`                     | Copied only when the file contains neither `Route::get('/test'` nor `Route::get('/user'`; otherwise left untouched            |
| `routes/web.php`                     | Copied only when the file does not already contain `LaravelLocalization::setLocale()`; otherwise left untouched               |
| `routes/console.php`                 | Copied only when the file does not already contain `Schedule::command('app:update-geoip-database')`; otherwise left untouched |
| `app/Http/Middleware/EnsureUser.php` | Copied only when the file does not already contain `!$request->user() instanceof User`; otherwise left untouched              |
| `.cursor/mcp.json`                   | Copied only when the file does not already contain `"mcpServers"`; otherwise left untouched (see [MCP servers](#mcp-servers)) |

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

### MCP servers

`starter:publish` seeds `.cursor/mcp.json` with three servers when the file has no `mcpServers` yet. The file stays git-ignored, so each developer keeps their own copy.

| Server          | Notes                                                                                                                                            |
|-----------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `laravel-boost` | Runs `php artisan boost:mcp` from the project root.                                                                                              |
| `herd`          | Uses `${userHome}/.config/herd/bin/herd-mcp.phar` and `${workspaceFolder}` as `SITE_PATH`. Adjust the phar path if Herd lives elsewhere (macOS). |
| `obscura`       | Expects the `obscura` binary on `PATH`. Remove the entry if Obscura is not installed.                                                            |

### Laravel Boost

The generated AI guidelines and the Boost MCP configs are **not** published — they embed machine-specific absolute paths and version-pinned guidelines, and are git-ignored (`.cursor/`, `.ai/`, `AGENTS.md`). Regenerate them locally after publishing:

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

### Graphify (code knowledge graph)

Graphify turns the project's code into a knowledge graph that an AI agent can query instead of grepping through files. The graph is stored in `graphify-out/`, which the published `.gitignore` already excludes. This section assumes the `graphify` CLI is installed globally.

#### First-time setup

Run once per project clone, from the project root, after `starter:publish` and `git init`:

```bash
graphify extract . --code-only
graphify cluster-only . --no-label
graphify hook install
graphify cursor install
```

| Command                              | What it does                                                                                           |
|--------------------------------------|--------------------------------------------------------------------------------------------------------|
| `graphify extract . --code-only`     | Parses the code locally and builds the graph. No LLM and no API key.                                   |
| `graphify cluster-only . --no-label` | Groups the graph into communities and writes `graphify-out/GRAPH_REPORT.md` and `graph.html`.          |
| `graphify hook install`              | Installs git hooks that rebuild the graph automatically (hooks are per clone, not shared via git).     |
| `graphify cursor install`            | Writes `.cursor/rules/graphify.mdc` (git-ignored) so the agent queries the graph before reading files. |

The graph, the hooks, and the Cursor rule are local to each machine and are not committed, because they only work where Graphify is installed. `hook install` also appends a `merge=graphify` line to `.gitattributes`; since `graphify-out/` is git-ignored, it has no effect and you can leave it uncommitted (`git checkout -- .gitattributes`). For agents other than Cursor, use the matching command from `graphify --help` (for example `graphify claude install`).

#### During development

Nothing to run: the hooks refresh the graph after every commit, branch switch, pull, merge, and rebase.

The only exception is uncommitted work. To let the agent see it, refresh the graph manually:

```bash
graphify update .
```

Use `graphify update . --force` after a refactor that deleted a lot of code, and `graphify watch .` to rebuild continuously while you work.

#### Using the graph

The agent runs these on its own once the Cursor rule is installed. You can run them too; replace the placeholders with class or method names from your project:

| Command                        | Purpose                                                 |
|--------------------------------|---------------------------------------------------------|
| `graphify query "<question>"`  | Part of the graph relevant to a question                |
| `graphify path "<A>" "<B>"`    | Shortest dependency path between two symbols            |
| `graphify explain "<symbol>"`  | Plain-language description of a node and its neighbors  |
| `graphify affected "<symbol>"` | What breaks if this symbol changes                      |
| `graphify god-nodes --top 10`  | The most connected classes (architectural hubs)         |

Open `graphify-out/graph.html` in a browser for a visual overview.

#### Notes

- `--code-only` indexes only source code (classes, methods, and the imports, calls, and inheritance between them). Files ignored by `.gitignore` are skipped; add a `.graphifyignore` to exclude anything else.
- To also index docs and other non-code files, run `graphify extract .` with a backend API key set (for example `ANTHROPIC_API_KEY`), or `/graphify .` from the agent. The automatic hooks refresh code only, not docs.
- `graphify cluster-only . --no-label` keeps placeholder names like `Community 3`. Drop `--no-label` (with an API key) to have an LLM name them.

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
