<?php

namespace App\Providers;

use App\Auth\Oidc\OidcGuard;
use App\Auth\Oidc\TokenVerifier;
use App\Auth\Oidc\UserResolver;
use App\Blog\PostRepository;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PostRepository::class, fn () => new PostRepository(
            resource_path('posts'),
        ));

        $this->app->singleton(TokenVerifier::class, fn (Application $app) => new TokenVerifier(
            issuer: is_string($issuer = config('services.oidc.issuer')) && $issuer !== '' ? $issuer : null,
            audience: config()->string('services.oidc.audience'),
            rolesClient: config()->string('services.oidc.roles_client'),
            leeway: config()->integer('services.oidc.leeway'),
            cache: $app->make(Cache::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSingleSignOn();
        $this->configureRateLimiting();
    }

    /**
     * The "oidc" guard: API requests authenticate with an SSO realm access token.
     */
    protected function configureSingleSignOn(): void
    {
        Auth::extend('oidc', function (Application $app): OidcGuard {
            $guard = new OidcGuard(
                $app->make(TokenVerifier::class),
                $app->make(UserResolver::class),
                $app->make(Request::class),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
