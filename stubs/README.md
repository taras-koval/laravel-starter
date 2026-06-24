<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Trusted Proxies

This application reads the client IP from `X-Forwarded-*` headers when running
behind a reverse proxy or load balancer. To prevent IP spoofing, you must tell
Laravel which proxies it should trust via the `TRUSTED_PROXIES` env variable.

### Configuration

| Value                                | Behavior                                                                                                                 |
|--------------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| `TRUSTED_PROXIES=` *(empty)*         | Don't trust any proxy. `X-Forwarded-*` headers are ignored and `request()->ip()` returns the real TCP remote address.    |
| `TRUSTED_PROXIES=*`                  | Trust **any** proxy. Use only when you control the network path (e.g., behind AWS ELB or Heroku where proxy IPs rotate). |
| `TRUSTED_PROXIES=1.2.3.4,10.0.0.0/8` | Trust the listed IPs / CIDR ranges only.                                                                                 |

### When to use what

- **Local development (Herd, `php artisan serve`, no proxy)** — leave it empty.
- **Local geo testing** — set `TRUSTED_PROXIES=*` temporarily so you can send a fake `X-Forwarded-For: 8.8.8.8` header via Postman to exercise GeoIP code paths. Revert to empty afterwards.
- **Behind Cloudflare** — set `TRUSTED_PROXIES` to the official [Cloudflare IP ranges](https://www.cloudflare.com/ips/), comma-separated, or `*` if Cloudflare is your only ingress.
- **Behind AWS ELB / Heroku / Fly.io** — set `TRUSTED_PROXIES=*` (these platforms don't expose stable proxy IPs).
- **Direct VPS without any proxy** — leave it empty. This makes header-based IP spoofing impossible.

### Why it matters

Without trusted-proxy configuration, an attacker can send a `X-Forwarded-For: <random>` header on each request. Laravel will read this as the client IP and use it for:

- Rate limiting (login throttle, API throttle) — circumvented.
- GeoIP detection — falsified country/city in `personal_access_tokens`.
- Audit logs — useless for tracking the real source.

Trusting only specific proxies makes the spoofing attempt fall back to the real `REMOTE_ADDR`.

## Session management

Each successful `POST /api/login` and `POST /api/register` issues a Sanctum personal-access token. The application exposes endpoints to let an authenticated user inspect and revoke the active sessions tied to those tokens.

### Endpoints

| Method   | URL                  | Behavior                                                                                          |
|----------|----------------------|---------------------------------------------------------------------------------------------------|
| `GET`    | `/api/sessions`      | List the user's active sessions (device, browser, platform, geo, last used, current).             |
| `DELETE` | `/api/sessions/{id}` | Revoke a specific session. Deleting the current session logs the caller out.                      |
| `POST`   | `/api/logout`        | Revoke only the current session (the token used in the request).                                  |
| `POST`   | `/api/logout-all`    | Revoke every session **except the current one** (Google-style "Sign out from all other devices"). |

> Migration note: prior to this change `POST /api/logout-all` revoked **every** session including the current one. The endpoint URL is unchanged, but the behavior now keeps the caller logged in. If you need the old "logout everywhere" behavior, call `POST /api/logout-all` followed by `POST /api/logout`.

### Automatic deduplication by `device_name`

Every successful `POST /api/login` revokes any existing token for the same user with the same `name` before issuing the new one. This keeps the session list clean — one row per "device" — without requiring an explicit logout.

The `name` is either:

- the `device_name` value supplied in the request body, or
- a server-generated label derived from the User-Agent: `"Chrome on Windows (Desktop)"`, `"Safari on iOS (iPhone)"`, `"Firefox on Linux (Desktop)"`, etc.

#### Stable `device_name` from the client (recommended)

For best results the client should send a stable, per-device identifier as `device_name`. Generate a UUID once and persist it in `localStorage` (web), Keychain (iOS) or SecureStorage (Android):

```js
// Web client example
const deviceName = localStorage.getItem('deviceName')
    ?? crypto.randomUUID();
localStorage.setItem('deviceName', deviceName);

await fetch('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password, device_name: deviceName }),
});
```

This guarantees that every relogin from the same install replaces the previous token, and clearing browser storage / reinstalling the app yields a brand-new row in the session list.

#### Known limitation: collisions on identical devices

If the client does **not** send `device_name`, the server falls back to the auto-generated label. Two devices with the same browser + OS combination (e.g. two Windows PCs running Chrome) produce the **same** label, so a login on one will silently revoke the token on the other. The user will get a `401` from the second device on its next request and will need to re-authenticate.

This is the price of "one token per device-label" without a client-side device ID. If silent re-logins between identical machines are a concern for your application, send a stable `device_name` from the client as shown above.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
