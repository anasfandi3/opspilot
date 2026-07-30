<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Taylor Otwell',
            'email' => 'TAYLOR@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'Test device',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'taylor@example.com')
            ->assertJsonPath('message', 'Registration successful.')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'created_at', 'updated_at'], 'token']]);
        $this->assertDatabaseHas('users', ['email' => 'taylor@example.com']);
        $accessToken = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertNotNull($accessToken);
        $this->assertSame($response->json('data.user.id'), $accessToken->tokenable_id);
        $this->assertSame('Test device', $accessToken->name);
    }

    public function test_registration_validation_failures_return_json(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name'])
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_registration_rejects_duplicate_and_differently_cased_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'person@example.com']);

        foreach (['person@example.com', 'PERSON@example.com'] as $email) {
            $this->postJson('/api/v1/auth/register', [
                'name' => 'Another Person',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'device_name' => 'Test device',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_can_login_with_normalized_email(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'USER@example.com',
            'password' => 'password',
            'device_name' => 'Phone',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'Phone']);
    }

    public function test_incorrect_credentials_return_a_generic_error(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        foreach (['user@example.com', 'missing@example.com'] as $email) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'incorrect-password',
                'device_name' => 'Phone',
            ])->assertUnprocessable()->assertExactJson([
                'message' => 'The provided credentials are incorrect.',
                'errors' => ['email' => ['The provided credentials are incorrect.']],
            ]);
        }
    }

    public function test_authentication_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'user@example.com']);
        $payload = [
            'email' => 'user@example.com',
            'password' => 'incorrect-password',
            'device_name' => 'Phone',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_login_rate_limit_does_not_consume_registration_bucket(): void
    {
        User::factory()->create(['email' => 'user@example.com']);
        $loginPayload = [
            'email' => 'user@example.com',
            'password' => 'incorrect-password',
            'device_name' => 'Phone',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $loginPayload)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'Laptop',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_be_retrieved(): void
    {
        [$user, $token] = $this->userAndToken();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_unauthenticated_access_is_rejected_with_json(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_user_can_update_profile(): void
    {
        [$user, $token] = $this->userAndToken();

        $this->withToken($token)->patchJson('/api/v1/me', [
            'name' => 'Updated Name',
            'email' => 'UPDATED@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'updated@example.com']);
    }

    public function test_profile_validation_and_protected_fields_are_safe(): void
    {
        $other = User::factory()->create(['email' => 'other@example.com']);
        [$user, $token] = $this->userAndToken();
        $originalPassword = $user->password;

        $this->withToken($token)->patchJson('/api/v1/me', [
            'name' => '',
            'email' => strtoupper($other->email),
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);

        $this->withToken($token)->patchJson('/api/v1/me', [
            'name' => 'Allowed Name',
            'password' => 'malicious-change',
            'email_verified_at' => null,
            'id' => $other->id,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Allowed Name', $user->name);
        $this->assertSame($originalPassword, $user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame($other->id, $user->id);
    }

    public function test_user_can_change_password_without_revoking_existing_tokens(): void
    {
        [$user, $token] = $this->userAndToken();

        $this->withToken($token)->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk()->assertJsonPath('message', 'Password updated successfully.');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        [$user, $token] = $this->userAndToken();

        $this->withToken($token)->putJson('/api/v1/me/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_logout_revokes_only_the_current_token_and_another_token_remains_usable(): void
    {
        $user = User::factory()->create();
        $firstToken = $user->createToken('First device')->plainTextToken;
        $secondToken = $user->createToken('Second device')->plainTextToken;

        $this->withToken($firstToken)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNull(PersonalAccessToken::findToken($firstToken));
        $this->assertNotNull(PersonalAccessToken::findToken($secondToken));
        $this->app['auth']->forgetGuards();
        $this->withToken($firstToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($secondToken)->getJson('/api/v1/me')->assertOk();
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function userAndToken(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('Test device')->plainTextToken];
    }
}
