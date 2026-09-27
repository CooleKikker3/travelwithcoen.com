<?php

namespace App\Providers;

use App\Support\DatabaseOverridesLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Website texts edited in the CMS override the defaults in lang/.
        $this->app->extend('translation.loader', fn (Loader $loader) => new DatabaseOverridesLoader($loader));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
