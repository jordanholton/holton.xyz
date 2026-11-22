<?php

namespace App\Auth\Oidc;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Authenticates API requests with an SSO realm access token.
 *
 * Resolution is tied to the request instance, so a long-lived app (tests,
 * Octane) never serves one request's user or roles to the next.
 */
class OidcGuard implements Guard
{
    use GuardHelpers;

    private ?OidcToken $token = null;

    private ?Request $resolvedFor = null;

    public function __construct(
        private readonly TokenVerifier $verifier,
        private readonly UserResolver $users,
        private Request $request,
    ) {}

    public function user(): ?Authenticatable
    {
        $this->resolve();

        return $this->user;
    }

    /**
     * The verified token behind the current user, for role checks.
     */
    public function token(): ?OidcToken
    {
        $this->resolve();

        return $this->token;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    private function resolve(): void
    {
        if ($this->resolvedFor === $this->request) {
            return;
        }

        $this->resolvedFor = $this->request;
        $this->user = null;
        $this->token = null;

        $jwt = $this->request->bearerToken();

        if ($jwt === null) {
            return;
        }

        try {
            $this->token = $this->verifier->verify($jwt);
        } catch (InvalidTokenException) {
            return;
        }

        $this->user = $this->users->resolve($this->token);
    }
}
