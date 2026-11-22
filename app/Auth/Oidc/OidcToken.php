<?php

namespace App\Auth\Oidc;

/**
 * A verified OIDC access token from the SSO realm.
 */
final readonly class OidcToken
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public array $claims,
        private string $rolesClient,
    ) {}

    public function subject(): string
    {
        return is_string($this->claims['sub'] ?? null) ? $this->claims['sub'] : '';
    }

    public function email(): ?string
    {
        return is_string($this->claims['email'] ?? null) ? $this->claims['email'] : null;
    }

    public function emailVerified(): bool
    {
        return ($this->claims['email_verified'] ?? false) === true;
    }

    public function name(): string
    {
        foreach (['name', 'preferred_username', 'email'] as $claim) {
            if (is_string($this->claims[$claim] ?? null) && $this->claims[$claim] !== '') {
                return $this->claims[$claim];
            }
        }

        return $this->subject();
    }

    /**
     * Keycloak realm roles, client roles on the API client, and OAuth scopes,
     * so permissions can be granted whichever way the realm is set up.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        $realm = $this->claims['realm_access']['roles'] ?? [];
        $client = $this->claims['resource_access'][$this->rolesClient]['roles'] ?? [];
        $scopes = is_string($this->claims['scope'] ?? null) ? explode(' ', $this->claims['scope']) : [];

        $roles = array_merge(is_array($realm) ? $realm : [], is_array($client) ? $client : [], $scopes);

        return array_values(array_unique(array_filter($roles, is_string(...))));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }
}
