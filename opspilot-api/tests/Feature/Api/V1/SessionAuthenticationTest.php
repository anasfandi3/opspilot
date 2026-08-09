<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_endpoint_and_session_routes_use_the_standard_web_security_stack(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');

        foreach (['api.v1.auth.session.store', 'api.v1.auth.session.destroy'] as $routeName) {
            $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];
            $this->assertContains('web', $middleware);
        }

        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertContains('sanctum/csrf-cookie', config('cors.paths'));

        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/me')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_session_login_regenerates_the_session_and_authenticates_protected_api_requests(): void
    {
        config(['sanctum.stateful' => ['localhost:5173']]);
        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->withSession(['pre_login_marker' => true]);
        $previousSessionId = session()->getId();

        $login = $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/session', [
                'email' => 'USER@example.com',
                'password' => 'password',
            ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('message', 'Session login successful.')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.password');
        $sessionCookie = collect($login->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->app['auth']->forgetGuards();

        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_session_login_is_rejected_without_a_csrf_token_when_the_test_bypass_is_disabled(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        try {
            $this->postJson('/api/v1/auth/session', [
                'email' => 'user@example.com',
                'password' => 'password',
            ])->assertStatus(419);
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_session_login_rejects_invalid_credentials_without_creating_a_token(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/session', [
            'email' => 'user@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable()->assertExactJson([
            'message' => 'The provided credentials are incorrect.',
            'errors' => ['email' => ['The provided credentials are incorrect.']],
        ]);

        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_session_logout_invalidates_the_session_without_revoking_personal_access_tokens(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('API client')->plainTextToken;
        $this->actingAs($user, 'web')->withSession(['session_marker' => 'present']);

        $this->postJson('/api/v1/auth/session/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Session logout successful.');

        $this->assertGuest('web');
        $this->assertFalse(session()->has('session_marker'));
        $this->assertNotNull(PersonalAccessToken::findToken($plainTextToken));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($plainTextToken)
            ->postJson('/api/v1/auth/session/logout')
            ->assertUnauthorized();
        $this->assertNotNull(PersonalAccessToken::findToken($plainTextToken));
        $this->app['auth']->forgetGuards();
        $this->withToken($plainTextToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_existing_pat_login_and_logout_contract_remains_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'api@example.com']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'api@example.com',
            'password' => 'password',
            'device_name' => 'CLI',
        ])->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);
        $token = $login->json('data.token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($token));
    }
}
