<?php

namespace App\Auth\Oidc;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * Verifies access tokens issued by the SSO realm (any OIDC provider; the
 * defaults match Keycloak). Signing keys come from the realm's discovery
 * document and are cached, and re-fetched when the realm rotates keys.
 */
class TokenVerifier
{
    private const int DISCOVERY_TTL = 86400;

    private const int JWKS_TTL = 3600;

    /** Minimum seconds between forced JWKS refreshes for unknown key ids. */
    private const int REFRESH_COOLDOWN = 60;

    public function __construct(
        private readonly ?string $issuer,
        private readonly string $audience,
        private readonly string $rolesClient,
        private readonly int $leeway,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws InvalidTokenException
     */
    public function verify(string $jwt): OidcToken
    {
        $issuer = $this->issuer();
        $kid = $this->keyId($jwt);
        $keys = $this->keys();

        if ($kid !== null && ! isset($keys[$kid])) {
            $keys = $this->keys(refresh: true);
        }

        JWT::$leeway = $this->leeway;

        try {
            $payload = JWT::decode($jwt, $keys);
        } catch (Throwable $e) {
            throw new InvalidTokenException($e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $claims */
        $claims = json_decode((string) json_encode($payload), true);

        if (($claims['iss'] ?? null) !== $issuer) {
            throw new InvalidTokenException('Token was issued by a different realm.');
        }

        $audiences = (array) ($claims['aud'] ?? []);

        if (! in_array($this->audience, $audiences, true)) {
            throw new InvalidTokenException("Token is not intended for audience [{$this->audience}].");
        }

        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new InvalidTokenException('Token has no subject.');
        }

        return new OidcToken($claims, $this->rolesClient);
    }

    private function issuer(): string
    {
        if ($this->issuer === null || $this->issuer === '') {
            throw new ServiceUnavailableHttpException(message: 'Single sign-on is not configured.');
        }

        return rtrim($this->issuer, '/');
    }

    private function keyId(string $jwt): ?string
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new InvalidTokenException('Malformed token.');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);

        if (! is_array($header)) {
            throw new InvalidTokenException('Malformed token header.');
        }

        return is_string($header['kid'] ?? null) ? $header['kid'] : null;
    }

    /**
     * Signing keys from the realm's JWKS, keyed by key id.
     *
     * @return array<string, Key>
     */
    private function keys(bool $refresh = false): array
    {
        $cacheKey = $this->cacheKey('jwks');

        if ($refresh && $this->cache->add($this->cacheKey('jwks-refreshed'), true, self::REFRESH_COOLDOWN)) {
            $this->cache->forget($cacheKey);
        }

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = $this->cache->remember($cacheKey, self::JWKS_TTL, function (): array {
            $jwks = $this->fetch($this->discovery()['jwks_uri']);

            // Only signing keys; Keycloak also publishes encryption keys (use: enc).
            $keys = array_filter(
                is_array($jwks['keys'] ?? null) ? $jwks['keys'] : [],
                fn (mixed $key) => is_array($key) && ($key['use'] ?? 'sig') === 'sig',
            );

            return ['keys' => array_values($keys)];
        });

        try {
            return JWK::parseKeySet($jwks);
        } catch (Throwable $e) {
            throw new ServiceUnavailableHttpException(message: 'Identity provider returned unusable signing keys.', previous: $e);
        }
    }

    /**
     * @return array{jwks_uri: string}
     */
    private function discovery(): array
    {
        /** @var array{jwks_uri: string} */
        return $this->cache->remember($this->cacheKey('discovery'), self::DISCOVERY_TTL, function (): array {
            $document = $this->fetch($this->issuer().'/.well-known/openid-configuration');

            if (($document['issuer'] ?? null) !== $this->issuer() || ! is_string($document['jwks_uri'] ?? null)) {
                throw new ServiceUnavailableHttpException(message: 'Identity provider discovery document does not match the configured issuer.');
            }

            return ['jwks_uri' => $document['jwks_uri']];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $url): array
    {
        try {
            $json = Http::timeout(5)->acceptJson()->get($url)->throw()->json();
        } catch (RequestException|ConnectionException $e) {
            throw new ServiceUnavailableHttpException(message: 'Identity provider is unavailable.', previous: $e);
        }

        if (! is_array($json)) {
            throw new ServiceUnavailableHttpException(message: 'Identity provider returned an invalid response.');
        }

        /** @var array<string, mixed> $json */
        return $json;
    }

    private function cacheKey(string $name): string
    {
        return 'oidc:'.$name.':'.sha1($this->issuer());
    }
}
