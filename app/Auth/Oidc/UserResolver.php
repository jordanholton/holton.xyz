<?php

namespace App\Auth\Oidc;

use App\Models\User;

/**
 * Maps a realm identity to a local user, creating it on first sight.
 * The realm's "sub" claim is the stable link; name and email follow the realm.
 */
class UserResolver
{
    public function resolve(OidcToken $token): User
    {
        $user = User::query()->where('oidc_subject', $token->subject())->first()
            ?? $this->unlinkedUserWithVerifiedEmail($token)
            ?? new User;

        $user->oidc_subject = $token->subject();
        $user->name = $token->name();

        // Only take the email if no other account already uses it.
        $email = $token->email();

        if ($email !== null && ! User::query()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
            $user->email = $email;
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    /**
     * Adopt a pre-existing local account, but only when the realm vouches for the email.
     */
    private function unlinkedUserWithVerifiedEmail(OidcToken $token): ?User
    {
        $email = $token->email();

        if ($email === null || ! $token->emailVerified()) {
            return null;
        }

        return User::query()->where('email', $email)->whereNull('oidc_subject')->first();
    }
}
