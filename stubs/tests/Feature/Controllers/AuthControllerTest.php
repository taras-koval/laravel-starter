<?php

namespace Tests\Feature\Controllers;

use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Services\RequestMetadataService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * @see AuthController
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => '1234',
            'password_confirmation' => '1234',
            'device_name' => 'web-client',
        ];

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ])->postJson(route('register'), $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'user@example.com')->firstOrFail();

        $this->assertNotSame('', $user->timezone);

        $this->assertEquals(1, $user->tokens()->where('name', 'web-client')->count());

        $token = $user->tokens()->first();
        $this->assertSame('web-client', $token->name);
        $this->assertSame('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', $token->user_agent);
        $this->assertSame('127.0.0.1', $token->ip_address);
        $this->assertSame('desktop', $token->device_type);
        $this->assertSame('Chrome', $token->browser);
        $this->assertSame('Linux', $token->platform);
    }

    public function test_register_without_device_name_generates_automatic_name(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => '1234',
            'password_confirmation' => '1234',
        ];

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ])->postJson(route('register'), $payload);

        $response->assertCreated();

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $token = $user->tokens()->first();

        $this->assertSame('Safari on iOS (iPhone)', $token->name);
        $this->assertSame('mobile', $token->device_type);
        $this->assertSame('iPhone', $token->device);
    }

    public function test_register_without_device_name_falls_back_to_device_type_for_desktop(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ])->postJson(route('register'), [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => '1234',
            'password_confirmation' => '1234',
        ]);

        $response->assertCreated();

        $token = User::where('email', 'user@example.com')->firstOrFail()->tokens()->first();
        $this->assertSame('Chrome on Windows (Desktop)', $token->name);
        $this->assertSame('desktop', $token->device_type);
        $this->assertNull($token->device);
    }

    public function test_register_with_custom_timezone(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => '1234',
            'password_confirmation' => '1234',
            'timezone' => 'America/New_York',
        ];

        $response = $this->postJson(route('register'), $payload);

        $response->assertCreated();

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertSame('America/New_York', $user->timezone);
    }

    public function test_login_with_valid_credentials_returns_token_and_metadata(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ])->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                'token',
            ]);

        $token = $user->tokens()->first();
        $this->assertSame('127.0.0.1', $token->ip_address);
        $this->assertSame('desktop', $token->device_type);
        $this->assertSame('Chrome', $token->browser);
        $this->assertSame('Windows', $token->platform);
    }

    public function test_login_with_device_name_replaces_existing_token(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $user->createToken('my-iphone');

        $response = $this->withHeaders([
            'User-Agent' => 'iPhone Safari',
        ])->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password',
            'device_name' => 'my-iphone',
        ]);

        $response->assertOk();

        $this->assertEquals(1, $user->tokens()->where('name', 'my-iphone')->count());
    }

    public function test_login_without_device_name_replaces_token_with_same_auto_name(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $autoName = app(RequestMetadataService::class)
            ->getMetadata('127.0.0.1', $userAgent)['device_name'];

        $previous = $user->createToken($autoName);

        $response = $this->withHeaders(['User-Agent' => $userAgent])
            ->postJson(route('login'), [
                'email' => 'user@example.com',
                'password' => 'password',
            ]);

        $response->assertOk();

        $tokens = $user->tokens()->where('name', $autoName)->get();
        $this->assertCount(1, $tokens);
        $this->assertNotSame($previous->accessToken->id, $tokens->first()->id);
    }

    public function test_login_with_invalid_credentials_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('api-client');
        $plainTextToken = $tokenResult->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->postJson(route('logout'))
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenResult->accessToken->id,
        ]);
    }

    public function test_logout_all_revokes_other_tokens_and_keeps_current(): void
    {
        $user = User::factory()->create();

        $user->createToken('device-a');
        $user->createToken('device-b');
        $current = $user->createToken('current-device');

        $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->postJson(route('logout-all'))
            ->assertNoContent();

        $remaining = $user->fresh()->tokens()->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($current->accessToken->id, $remaining->first()->id);
    }

    public function test_send_verification_email_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson(route('verification.send'))
            ->assertOk()
            ->assertJson(['message' => 'Verification link sent.']);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verify_email_with_valid_signature_marks_user_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJson(['message' => 'Email successfully verified.']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_forgot_password_sends_reset_link_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson(route('forgot-password'), ['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_with_unknown_email_returns_generic_response(): void
    {
        Notification::fake();

        $this->postJson(route('forgot-password'), ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson([
                'message' => 'If an account exists for this email, a password reset link has been sent.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_revokes_all_tokens(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $user->createToken('device-1');
        $user->createToken('device-2');

        $token = Password::createToken($user);

        $response = $this->postJson(route('reset-password'), [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => '1234',
            'password_confirmation' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertEquals(0, $user->fresh()->tokens()->count());

        $this->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => '1234',
        ])->assertOk();
    }

    public function test_verify_email_with_invalid_hash_returns_validation_error(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->getKey(),
                'hash' => 'invalid-hash',
            ],
        );

        $this->getJson($verificationUrl)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verify_email_with_already_verified_email_returns_message(): void
    {
        $user = User::factory()->create(); // Already verified by default

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJson(['message' => 'Email already verified.']);
    }

    public function test_send_verification_email_when_already_verified_returns_message(): void
    {
        $user = User::factory()->create(); // Already verified by default

        $this->actingAs($user)
            ->postJson(route('verification.send'))
            ->assertOk()
            ->assertJson(['message' => 'Email already verified.']);
    }

    public function test_login_with_nonexistent_email_returns_validation_error(): void
    {
        $response = $this->postJson(route('login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unverified_user_can_logout(): void
    {
        $user = User::factory()->unverified()->create();
        $tokenResult = $user->createToken('unverified-client');

        $this->withHeader('Authorization', 'Bearer ' . $tokenResult->plainTextToken)
            ->postJson(route('logout'))
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenResult->accessToken->id,
        ]);
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->postJson(route('login'), [
                'email' => 'limited@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson(route('login'), [
            'email' => 'limited@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_register_fires_registered_and_login_events(): void
    {
        Event::fake([Registered::class, Login::class]);

        $this->postJson(route('register'), [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => '1234',
            'password_confirmation' => '1234',
        ])->assertCreated();

        Event::assertDispatched(Registered::class);
        Event::assertDispatched(Login::class);
    }

    public function test_successful_login_fires_attempting_and_login_events(): void
    {
        Event::fake([Attempting::class, Login::class, Failed::class]);

        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ])->assertOk();

        Event::assertDispatched(Attempting::class);
        Event::assertDispatched(Login::class);
        Event::assertNotDispatched(Failed::class);
    }

    public function test_invalid_password_fires_failed_event(): void
    {
        Event::fake([Failed::class, Login::class]);

        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        Event::assertDispatched(Failed::class);
        Event::assertNotDispatched(Login::class);
    }

    public function test_unknown_email_fires_failed_event(): void
    {
        Event::fake([Failed::class, Login::class]);

        $this->postJson(route('login'), [
            'email' => 'ghost@example.com',
            'password' => 'whatever',
        ])->assertStatus(422);

        Event::assertDispatched(Failed::class);
        Event::assertNotDispatched(Login::class);
    }

    public function test_sanctum_expiration_invalidates_old_tokens(): void
    {
        config()->set('sanctum.expiration', 30 * 24 * 60); // 30 days

        $user = User::factory()->create();
        $token = $user->createToken('test');

        // Make the token look 31 days old.
        $token->accessToken->forceFill(['created_at' => now()->subDays(31)])->save();

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson(route('user.show'))
            ->assertUnauthorized();
    }

    public function test_verify_email_with_unknown_user_returns_validation_error(): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => 9999,
                'hash' => sha1('anything@example.com'),
            ],
        );

        $this->getJson($verificationUrl)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_list_their_active_sessions(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device');
        $user->createToken('another-device');

        $response = $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->getJson(route('sessions.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id', 'name', 'browser', 'platform', 'device', 'device_type',
                        'country', 'region', 'city', 'ip_address',
                        'last_used_at', 'created_at', 'is_current',
                    ],
                ],
            ]);
    }

    public function test_session_list_marks_current_session_with_is_current_true(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device');
        $other = $user->createToken('other-device');

        $response = $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->getJson(route('sessions.index'));

        $response->assertOk();

        $sessions = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($sessions[$current->accessToken->id]['is_current']);
        $this->assertFalse($sessions[$other->accessToken->id]['is_current']);
    }

    public function test_session_list_does_not_include_other_users_tokens(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $current = $user->createToken('mine');
        $other->createToken('not-mine');

        $response = $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->getJson(route('sessions.index'));

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('mine', $response->json('data.0.name'));
    }

    public function test_user_can_revoke_specific_session_by_id(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device');
        $target = $user->createToken('to-delete');

        $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->deleteJson(route('sessions.destroy', ['id' => $target->accessToken->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $target->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_revoking_other_users_session_returns_404(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $current = $user->createToken('current-device');
        $foreign = $other->createToken('foreign-device');

        $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->deleteJson(route('sessions.destroy', ['id' => $foreign->accessToken->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }

    public function test_user_can_revoke_their_current_session_via_endpoint(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device');

        $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->deleteJson(route('sessions.destroy', ['id' => $current->accessToken->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    public function test_unauthenticated_request_to_sessions_returns_401(): void
    {
        $this->getJson(route('sessions.index'))->assertUnauthorized();
        $this->deleteJson(route('sessions.destroy', ['id' => 1]))->assertUnauthorized();
    }
}
