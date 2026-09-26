<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());
        Model::shouldBeStrict(!app()->isProduction());

        RateLimiter::for('api', static function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('auth-login', static function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($email . '|' . $request->ip()),
            ];
        });

        $this->configureQueryMonitoring();
        $this->configurePasswordResetUrl();
        $this->configureTrustedProxies();
        $this->configureMacros();

        Password::defaults(static function () {
            return app()->isProduction()
                ? Password::min(8)->numbers()->letters()
                : Password::min(4);
        });
    }

    private function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');

        if ($proxies !== null && $proxies !== '') {
            TrustProxies::at($proxies);
        }
    }

    private function configureQueryMonitoring(): void
    {
        DB::whenQueryingForLongerThan(200, static function (Connection $connection) {
            Log::warning('Total query time exceeded threshold', [
                'connection' => $connection->getName(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'query_count' => $connection->totalQueryDuration() > 0 ? $connection->getQueryLog() : null,
            ]);
        });

        DB::listen(static function ($query) {
            if ($query->time > 50) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                ]);
            }
        });
    }

    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(static function ($notifiable, $token) {
            return config('app.frontend_url') . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }

    private function configureMacros(): void
    {
        Carbon::macro('toUserDate', function (?string $timezone = null, bool $humanReadable = false) {
            $timezone ??= auth()->user()->timezone ?? config('app.timezone');
            $date = $this->timezone($timezone);

            if ($humanReadable && ($date->isToday() || $date->isYesterday())) {
                return $date->diffForHumans();
            }

            return $date->format('M j, Y');
        });

        Carbon::macro('toUserDateTime', function (?string $timezone = null, bool $humanReadable = false) {
            $timezone ??= auth()->user()->timezone ?? config('app.timezone');
            $date = $this->timezone($timezone);

            if ($humanReadable && $date->diffInHours() < 24) {
                return $date->diffForHumans();
            }

            return $date->format('M j, Y g:i A');
        });
    }
}
