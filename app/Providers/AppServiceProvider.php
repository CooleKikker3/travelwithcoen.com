<?php

namespace App\Providers;

use App\Models\User;
use App\Support\DatabaseOverridesLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Country outlines are loaded once per request.
        $this->app->scoped(\App\Support\CountryLocator::class);

        // Website texts edited in the CMS override the defaults in lang/.
        $this->app->extend('translation.loader', fn (Loader $loader) => new DatabaseOverridesLoader($loader));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind Cloudflare: trust its forwarding headers (config, so it also works with config:cache).
        if ($proxies = config('travel.trusted_proxies')) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : explode(',', $proxies));
        }

        Gate::define('see-live-tracking', fn (User $user) => $user->canSeeLiveTracking());
    }
}
