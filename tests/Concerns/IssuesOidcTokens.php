<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * Stands in for the SSO realm: publishes a discovery document and JWKS via
 * Http::fake and signs access tokens with a local RSA key.
 */
trait IssuesOidcTokens
{
    protected string $issuer = 'https://sso.test/realms/holton';

    protected string $audience = 'holton-xyz-api';

    /** @var array<string, OpenSSLAsymmetricKey> Shared across tests; RSA key generation is slow. */
    private static array $signingKeys = [];

    /** @var list<string> Key ids currently published in the JWKS. */
    protected array $publishedKids = [];

    /** Simulate the identity provider being unreachable. */
    protected bool $realmDown = false;

    protected function setUpRealm(): void
    {
        config([
            'services.oidc.issuer' => $this->issuer,
            'services.oidc.audience' => $this->audience,
            'services.oidc.roles_client' => $this->audience,
        ]);

        $this->publishedKids = [$this->signingKey('key-1')];

        Http::fake([
            "{$this->issuer}/.well-known/openid-configuration" => fn () => $this->realmDown ? Http::response(status: 502) : Http::response([
                'issuer' => $this->issuer,
                'jwks_uri' => "{$this->issuer}/protocol/openid-connect/certs",
            ]),
            "{$this->issuer}/protocol/openid-connect/certs" => fn () => $this->realmDown ? Http::response(status: 502) : Http::response([
                'keys' => array_map($this->jwk(...), $this->publishedKids),
            ]),
        ]);
    }

    /**
     * A realm access token. Override any claim, or pass null to drop it.
     *
     * @param  list<string>  $roles  Realm roles
     * @param  array<string, mixed>  $claims
     */
    protected function realmToken(array $roles = ['posts:read', 'posts:write'], array $claims = [], string $kid = 'key-1'): string
    {
        $now = time();

        $payload = array_filter([
            'iss' => $this->issuer,
            'aud' => [$this->audience, 'account'],
            'sub' => 'user-123',
            'typ' => 'Bearer',
            'azp' => 'holton-xyz-mobile',
            'iat' => $now,
            'exp' => $now + 300,
            'email' => 'jordan@example.com',
            'email_verified' => true,
            'name' => 'Jordan Holton',
            'preferred_username' => 'jordan',
            'scope' => 'openid profile email',
            'realm_access' => ['roles' => $roles],
            ...$claims,
        ], fn ($value) => $value !== null);

        openssl_pkey_export(self::$signingKeys[$this->signingKey($kid)], $pem);

        return JWT::encode($payload, $pem, 'RS256', $kid);
    }

    protected function signingKey(string $kid): string
    {
        self::$signingKeys[$kid] ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA])
            ?: throw new \RuntimeException('Could not generate a test key.');

        return $kid;
    }

    /**
     * @return array<string, string>
     */
    private function jwk(string $kid): array
    {
        $details = openssl_pkey_get_details(self::$signingKeys[$kid]) ?: [];

        return [
            'kid' => $kid,
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }
}
