<?php

namespace Tests\Feature\Api;

use App\Auth\Oidc\TokenVerifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\IssuesOidcTokens;
use Tests\TestCase;

class OidcAuthTest extends TestCase
{
    use IssuesOidcTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRealm();
    }

    public function test_realm_token_signs_the_user_in_and_provisions_them(): void
    {
        $this->withToken($this->realmToken())
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.name', 'Jordan Holton')
            ->assertJsonPath('data.email', 'jordan@example.com');

        $user = User::query()->sole();
        $this->assertSame('user-123', $user->oidc_subject);
        $this->assertNull($user->password);
    }

    public function test_profile_changes_in_the_realm_follow_the_user(): void
    {
        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertOk();
        $this->withToken($this->realmToken(claims: ['name' => 'J. Holton', 'email' => 'j@example.com']))
            ->getJson('/api/v1/user')
            ->assertJsonPath('data.name', 'J. Holton')
            ->assertJsonPath('data.email', 'j@example.com');

        $this->assertSame(1, User::query()->count());
    }

    public function test_existing_account_is_linked_only_by_a_verified_email(): void
    {
        $existing = User::factory()->create(['email' => 'jordan@example.com']);

        $this->withToken($this->realmToken(claims: ['sub' => 'unverified', 'email_verified' => false]))
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', null);
        $this->assertNull($existing->fresh()?->oidc_subject);

        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertJsonPath('data.id', $existing->id);
        $this->assertSame('user-123', $existing->fresh()?->oidc_subject);
    }

    public function test_missing_token_gets_a_json_401(): void
    {
        // No Accept header, as a bare HTTP client would send.
        $this->get('/api/v1/posts')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function rejectedClaims(): array
    {
        return [
            'expired' => [['iat' => time() - 3600, 'exp' => time() - 600]],
            'other realm' => [['iss' => 'https://sso.test/realms/someone-else']],
            'other audience' => [['aud' => 'account']],
            'no subject' => [['sub' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    #[DataProvider('rejectedClaims')]
    public function test_untrusted_tokens_are_rejected(array $claims): void
    {
        $this->withToken($this->realmToken(claims: $claims))->getJson('/api/v1/user')->assertUnauthorized();
        $this->assertSame(0, User::query()->count());
    }

    public function test_tampered_and_garbage_tokens_are_rejected(): void
    {
        [$header, $payload, $signature] = explode('.', $this->realmToken(roles: []));
        $elevated = rtrim(strtr(base64_encode((string) json_encode([
            ...json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true),
            'realm_access' => ['roles' => ['posts:write']],
        ])), '+/', '-_'), '=');

        $this->withToken("{$header}.{$elevated}.{$signature}")->getJson('/api/v1/user')->assertUnauthorized();
        $this->withToken('not-a-jwt')->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_token_signed_by_an_unpublished_key_is_rejected(): void
    {
        $this->withToken($this->realmToken(kid: 'rogue'))->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_rotated_signing_keys_are_picked_up(): void
    {
        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertOk();

        // The realm rotates to a new key; the cached JWKS doesn't have it yet.
        $this->publishedKids[] = $this->signingKey('key-2');

        $this->withToken($this->realmToken(kid: 'key-2'))->getJson('/api/v1/user')->assertOk();
    }

    public function test_keys_are_cached_between_requests(): void
    {
        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertOk();
        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertOk();

        Http::assertSentCount(2); // one discovery document, one JWKS
    }

    public function test_roles_can_come_from_the_api_client(): void
    {
        $token = $this->realmToken(roles: [], claims: [
            'resource_access' => [$this->audience => ['roles' => ['posts:read']]],
        ]);

        $this->withToken($token)->getJson('/api/v1/posts')->assertOk();
        $this->withToken($token)->postJson('/api/v1/posts', ['title' => 'x', 'body' => 'y'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing role [posts:write].');
    }

    public function test_user_without_roles_cannot_read_posts(): void
    {
        $this->withToken($this->realmToken(roles: []))->getJson('/api/v1/posts')->assertForbidden();
    }

    public function test_unreachable_realm_is_a_503_not_a_sign_out(): void
    {
        $this->realmDown = true;

        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertServiceUnavailable();
    }

    public function test_unconfigured_sso_is_a_503(): void
    {
        config(['services.oidc.issuer' => null]);
        $this->app->forgetInstance(TokenVerifier::class);

        $this->withToken($this->realmToken())->getJson('/api/v1/user')->assertServiceUnavailable();
    }
}
