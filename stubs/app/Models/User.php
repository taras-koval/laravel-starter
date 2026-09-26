<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

class User extends Authenticatable // implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use MustVerifyEmailTrait;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'timezone' => 'UTC',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Tokens that Sanctum would still accept: not past their own "expires_at"
     * and not older than the global "sanctum.expiration" window.
     *
     * @return MorphMany<PersonalAccessToken, $this>
     */
    public function activeTokens(): MorphMany
    {
        $expirationMinutes = config('sanctum.expiration');

        return $this->tokens()
            ->where(static function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($expirationMinutes, static function (Builder $query) use ($expirationMinutes): void {
                $query->where('created_at', '>', now()->subMinutes((int) $expirationMinutes));
            });
    }

    /**
     * Revoke every token except the one used for the current request.
     * Falls back to revoking all tokens when the request was not authenticated
     * with a persisted token (e.g. session guard / transient token).
     */
    public function revokeOtherTokens(): void
    {
        $currentToken = $this->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null;

        $this->tokens()
            ->when($currentTokenId !== null, static function (Builder $query) use ($currentTokenId): void {
                $query->whereKeyNot($currentTokenId);
            })->delete();
    }
}
