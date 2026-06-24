<?php

namespace Tests\Feature\Controllers;

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @see UserController
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_partially_update_profile_without_email_or_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'password' => 'password1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', $user->email);

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_changing_email_resets_verification_and_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'password1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'current_password' => 'password1234',
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_changing_email_without_current_password_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'password1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_changing_email_with_wrong_current_password_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'password1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'current_password' => 'not-the-real-password',
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_submitting_unchanged_email_does_not_require_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'same@example.com',
            'name' => 'Old Name',
            'password' => 'password1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'email' => 'same@example.com',
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_empty_email_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'password1234',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson(route('user.update'), [
            'current_password' => 'password1234',
            'email' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->patchJson(route('user.update'), [
            'current_password' => 'password1234',
            'email' => null,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_empty_name_returns_validation_error(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user);

        $this->patchJson(route('user.update'), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->patchJson(route('user.update'), ['name' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertSame('Old Name', $user->fresh()->name);
    }

    public function test_email_change_revokes_other_tokens_but_preserves_current(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'password1234',
        ]);

        $current = $user->createToken('current');
        $currentTokenId = $current->accessToken->id;
        $user->createToken('other-1');
        $user->createToken('other-2');

        $response = $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->patchJson(route('user.update'), [
                'current_password' => 'password1234',
                'email' => 'new@example.com',
            ]);

        $response->assertOk();

        $remainingIds = $user->tokens()->pluck('id')->all();
        $this->assertSame([$currentTokenId], $remainingIds);
    }

    public function test_changing_password_requires_all_fields_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'password' => 'oldpass1234',
        ]);
        Sanctum::actingAs($user);

        // create a token to ensure it's revoked after a password change
        $user->createToken('test');
        $this->assertGreaterThan(0, $user->tokens()->count());

        $response = $this->patchJson(route('user.update'), [
            'current_password' => 'oldpass1234',
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_missing_password_confirmation_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'password' => 'oldpass1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'current_password' => 'oldpass1234',
            'password' => 'newpass1234',
            // missing password_confirmation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_wrong_current_password_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'password' => 'oldpass1234',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson(route('user.update'), [
            'current_password' => 'wrong-password',
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_deletes_other_tokens_but_preserves_current(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        // Create the "current" token and two extra tokens
        $current = $user->createToken('current');
        $currentTokenId = $current->accessToken->id;
        $user->createToken('other-1');
        $user->createToken('other-2');

        // Use Bearer token so currentAccessToken() is set for the request
        $response = $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->patchJson(route('user.update'), [
                'current_password' => 'password',
                'password' => 'newpass1234',
                'password_confirmation' => 'newpass1234',
            ]);

        $response->assertOk();

        $remainingIds = $user->tokens()->pluck('id')->all();
        $this->assertSame([$currentTokenId], $remainingIds);
    }
}
