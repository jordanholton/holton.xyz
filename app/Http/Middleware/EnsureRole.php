<?php

namespace App\Http\Middleware;

use App\Auth\Oidc\OidcGuard;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Requires every listed role on the request's SSO token, e.g. "role:posts:write".
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $guard = Auth::guard('api');
        $token = $guard instanceof OidcGuard ? $guard->token() : null;

        if ($token === null) {
            throw new AuthenticationException;
        }

        foreach ($roles as $role) {
            if (! $token->hasRole($role)) {
                throw new AccessDeniedHttpException("Missing role [{$role}].");
            }
        }

        return $next($request);
    }
}
